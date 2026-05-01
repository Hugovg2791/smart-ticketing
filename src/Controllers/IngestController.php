<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\GmailService;
use App\Core\GeminiService;
use App\Models\TicketModel;
use App\Models\MessageModel;
use App\Models\AiSuggestionModel;
use Exception;

/**
 * Controlador encargado de orquestar el pipeline completo de ingesta:
 * obtener correos de Gmail → detectar hilos → persistir en base de datos → análisis IA.
 */
class IngestController extends Controller {

    /**
     * Ejecuta el proceso de ingesta de correos no leídos.
     * Para cada correo: detecta si pertenece a un hilo existente o crea uno nuevo,
     * y luego invoca a Gemini para analizar el mensaje.
     */
    public function ingest() {
        try {
            $gmailService = new GmailService();

            // Verificar que tenemos un token válido
            if ($gmailService->getClient()->isAccessTokenExpired() && !$gmailService->getClient()->getRefreshToken()) {
                $this->json([
                    'status' => 'error',
                    'message' => 'Gmail not authenticated. Visit /gmail/auth first.'
                ], 401);
                return;
            }

            $emails = $gmailService->getUnreadMessages();
            $ticketModel = new TicketModel();
            $messageModel = new MessageModel();
            $aiSuggestionModel = new AiSuggestionModel();
            $geminiService = new GeminiService();

            $processed = [];

            foreach ($emails as $email) {
                try {
                    // Verificar si el mensaje ya existe en la base de datos
                    $existing = $messageModel->findByMessageId($email['message_id']);
                    if ($existing) {
                        continue; // Ya procesado, saltar
                    }

                    $ticketId = null;

                    // Detección de hilo: buscar si thread_id coincide con un ticket existente
                    if (!empty($email['thread_id'])) {
                        $existingTicket = $ticketModel->findByGmailThreadId($email['thread_id']);
                        if ($existingTicket) {
                            $ticketId = (int) $existingTicket['id'];
                        }
                    }

                    // Fallback: si no hay thread_id o no existe ticket, intentar por In-Reply-To
                    if ($ticketId === null && !empty($email['in_reply_to'])) {
                        $ticketId = $messageModel->findTicketByInReplyTo($email['in_reply_to']);
                    }

                    // Si no pertenece a un hilo existente, crear un ticket nuevo
                    if ($ticketId === null) {
                        $ticketId = $ticketModel->create($email['subject'], 'No leído', $email['thread_id'] ?? null);
                    }

                    // Extraer el email del remitente (puede venir como "Nombre <email>")
                    $senderEmail = $email['from'];
                    if (preg_match('/<(.+?)>/', $email['from'], $matches)) {
                        $senderEmail = $matches[1];
                    }

                    // Persistir el mensaje
                    $newMessageId = $messageModel->create(
                        $ticketId,
                        $senderEmail,
                        $email['body_text'],
                        $email['message_id'],
                        $email['in_reply_to'] ?: null
                    );

                    // Actualizar el timestamp del ticket y marcar mensajes nuevos
                    $ticketModel->updateTimestamp($ticketId);
                    $ticketModel->markNewMessage($ticketId);

                    // Análisis IA con Gemini (no bloquea la ingesta si falla)
                    $aiResult = null;
                    try {
                        // Obtener historial de la conversación para dar contexto al LLM
                        $conversationHistory = $messageModel->findByTicketId($ticketId);
                        // Excluir el mensaje actual del historial
                        $conversationHistory = array_filter($conversationHistory, function($msg) use ($newMessageId) {
                            return $msg['id'] != $newMessageId;
                        });

                        $aiResult = $geminiService->analyzeMessage($email['body_text'], array_values($conversationHistory));

                        if ($aiResult) {
                            $aiSuggestionModel->create(
                                $newMessageId,
                                $aiResult['category'],
                                $aiResult['sentiment'],
                                $aiResult['suggested_response']
                            );
                        }
                    } catch (Exception $e) {
                        error_log('IngestController: Error en análisis Gemini para mensaje ' . $newMessageId . ' - ' . $e->getMessage());
                    }

                    $processed[] = [
                        'subject'      => $email['subject'],
                        'from'         => $email['from'],
                        'ticket_id'    => $ticketId,
                        'message_id'   => $newMessageId,
                        'is_reply'     => !empty($email['in_reply_to']),
                        'ai_analysis'  => $aiResult
                    ];
                } catch (Exception $e) {
                    error_log('IngestController: Error procesando el correo ' . ($email['message_id'] ?? 'desconocido') . ' - ' . $e->getMessage());
                }
            }

            $this->json([
                'status'    => 'success',
                'processed' => count($processed),
                'emails'    => $processed
            ]);

        } catch (Exception $e) {
            $this->json([
                'status'  => 'error',
                'message' => 'Ingestion failed',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sincronización manual desde el dashboard.
     * Ejecuta el pipeline de ingesta y redirige al listado con un mensaje flash.
     * POST /ingest/sync
     */
    public function ingestWeb() {
        try {
            $gmailService = new GmailService();

            // Verificar autenticación de Gmail
            if ($gmailService->getClient()->isAccessTokenExpired() && !$gmailService->getClient()->getRefreshToken()) {
                $_SESSION['flash_error'] = 'Gmail no está autenticado. Ve a Ajustes para conectar tu cuenta.';
                header('Location: /tickets');
                exit;
            }

            $emails = $gmailService->getUnreadMessages();
            $ticketModel = new TicketModel();
            $messageModel = new MessageModel();
            $aiSuggestionModel = new AiSuggestionModel();
            $geminiService = new GeminiService();

            $processedCount = 0;

            foreach ($emails as $email) {
                try {
                    $existing = $messageModel->findByMessageId($email['message_id']);
                    if ($existing) {
                        continue;
                    }

                    $ticketId = null;

                    if (!empty($email['thread_id'])) {
                        $existingTicket = $ticketModel->findByGmailThreadId($email['thread_id']);
                        if ($existingTicket) {
                            $ticketId = (int) $existingTicket['id'];
                        }
                    }

                    if ($ticketId === null && !empty($email['in_reply_to'])) {
                        $ticketId = $messageModel->findTicketByInReplyTo($email['in_reply_to']);
                    }

                    if ($ticketId === null) {
                        $ticketId = $ticketModel->create($email['subject'], 'No leído', $email['thread_id'] ?? null);
                    }

                    $senderEmail = $email['from'];
                    if (preg_match('/<(.+?)>/', $email['from'], $matches)) {
                        $senderEmail = $matches[1];
                    }

                    $newMessageId = $messageModel->create(
                        $ticketId,
                        $senderEmail,
                        $email['body_text'],
                        $email['message_id'],
                        $email['in_reply_to'] ?: null
                    );

                    $ticketModel->updateTimestamp($ticketId);
                    $ticketModel->markNewMessage($ticketId);

                    // Análisis IA con Gemini
                    try {
                        $conversationHistory = $messageModel->findByTicketId($ticketId);
                        $conversationHistory = array_filter($conversationHistory, function($msg) use ($newMessageId) {
                            return $msg['id'] != $newMessageId;
                        });

                        $aiResult = $geminiService->analyzeMessage($email['body_text'], array_values($conversationHistory));

                        if ($aiResult) {
                            $aiSuggestionModel->create(
                                $newMessageId,
                                $aiResult['category'],
                                $aiResult['sentiment'],
                                $aiResult['suggested_response']
                            );
                        }
                    } catch (Exception $e) {
                        error_log('IngestController: Error en análisis Gemini para mensaje ' . $newMessageId . ' - ' . $e->getMessage());
                    }

                    $processedCount++;
                } catch (Exception $e) {
                    error_log('IngestController: Error procesando el correo ' . ($email['message_id'] ?? 'desconocido') . ' - ' . $e->getMessage());
                }
            }

            if ($processedCount > 0) {
                $_SESSION['flash_success'] = "Se han procesado {$processedCount} correo(s) nuevo(s) correctamente.";
            } else {
                $_SESSION['flash_success'] = 'No hay correos nuevos por procesar.';
            }

        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Error al sincronizar correos: ' . $e->getMessage();
        }

        header('Location: /tickets');
        exit;
    }
}

