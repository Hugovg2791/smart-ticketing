<?php
namespace App\Core;

/**
 * Servicio encargado de comunicarse con la API de Google Gemini
 * para analizar correos entrantes: determinar categoría, sentimiento
 * y generar una respuesta sugerida.
 *
 * Implementa un sistema RAG (Retrieval-Augmented Generation) inyectando
 * la base de conocimiento de políticas de la empresa en cada llamada
 * para que las respuestas se basen en reglas reales y no en "alucinaciones".
 */
class GeminiService
{
    private string $apiKey;
    private string $apiUrl;

    /** @var string|null Contenido cacheado de la base de conocimiento */
    private ?string $knowledgeBase = null;

    public function __construct()
    {
        $this->apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:streamGenerateContent';
        $this->knowledgeBase = $this->loadKnowledgeBase();
    }

    /**
     * Carga el contenido de la base de conocimiento desde el archivo Markdown.
     * Si el archivo no existe o no es legible, registra una advertencia y devuelve null,
     * permitiendo al servicio funcionar con instrucciones genéricas (degradación elegante).
     *
     * @return string|null Contenido del archivo o null si no está disponible
     */
    private function loadKnowledgeBase(): ?string
    {
        // Ruta relativa desde src/core/ hasta la raíz del proyecto: ../../docs/knowledge_base.md
        $knowledgeBasePath = __DIR__ . '/../../docs/knowledge_base.md';

        if (!file_exists($knowledgeBasePath)) {
            error_log('GeminiService: Archivo de base de conocimiento no encontrado en ' . $knowledgeBasePath . '. Las respuestas se generarán sin contexto de políticas.');
            return null;
        }

        $content = file_get_contents($knowledgeBasePath);

        if ($content === false) {
            error_log('GeminiService: No se pudo leer el archivo de base de conocimiento en ' . $knowledgeBasePath);
            return null;
        }

        return $content;
    }

    /**
     * Analiza un mensaje de correo electrónico usando Gemini.
     * Devuelve un array con 'category', 'sentiment' y 'suggested_response',
     * o null si la llamada falla.
     *
     * @param string $messageText Texto limpio del mensaje a analizar
     * @param array  $conversationHistory Array de mensajes previos del mismo ticket (opcional)
     * @return array|null
     */
    public function analyzeMessage(string $messageText, array $conversationHistory = []): ?array
    {
        if (empty($this->apiKey)) {
            error_log('GeminiService: GEMINI_API_KEY no configurada');
            return null;
        }

        // 1. Obtenemos ÚNICAMENTE el texto del usuario (historial + correo actual)
        $userPrompt = $this->buildPrompt($messageText, $conversationHistory);

        // 2. Definimos las reglas estrictas de comportamiento en la instrucción del sistema
        //    Si la base de conocimiento está disponible, se inyecta como contexto RAG
        $systemInstructionText = $this->buildSystemInstruction();

        // 3. Construimos el cuerpo de la petición separando roles
        $requestBody = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstructionText]
                ]
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'thinkingConfig' => [
                    'thinkingLevel' => 'LOW'
                ],
                'responseMimeType' => 'application/json'
            ]
        ];

        $url = $this->apiUrl . '?key=' . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($requestBody),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            error_log('GeminiService: Error cURL - ' . $curlError);
            return null;
        }

        if ($httpCode !== 200) {
            error_log('GeminiService: HTTP ' . $httpCode . ' - ' . $response);
            return null;
        }

        return $this->parseResponse($response);
    }

    /**
     * Construye la instrucción de sistema completa para la API de Gemini.
     * Si la base de conocimiento está cargada, se inyecta como contexto RAG
     * y se exige a la IA que base sus respuestas estrictamente en el manual.
     * Si no está disponible, se usa una instrucción genérica.
     *
     * @return string Instrucción de sistema completa
     */
    private function buildSystemInstruction(): string
    {
        $baseInstruction = "Eres el cerebro de un sistema de 'Smart Ticketing' para una tienda de electrónica. " .
            "Tu tarea es leer el contenido de correos electrónicos de clientes. " .
            "Debes analizar el texto y devolver estricta y únicamente un objeto JSON con exactamente estos tres campos:\n" .
            "1. \"category\": (Facturación, Error Técnico, Consulta General, Solicitud de Funcionalidad, Queja, Otro).\n" .
            "2. \"sentiment\": (positivo, neutral, negativo).\n" .
            "3. \"suggested_response\": Una respuesta profesional y empática en español.\n" .
            "Responde ÚNICAMENTE con el objeto JSON, sin explicaciones ni texto adicional.";

        // Si la base de conocimiento no está disponible, usar instrucción genérica
        if ($this->knowledgeBase === null) {
            return $baseInstruction;
        }

        // Inyectar la base de conocimiento como contexto RAG con instrucciones estrictas
        return "=== MANUAL DE POLÍTICAS DE LA EMPRESA (BASE DE CONOCIMIENTO) ===\n\n" .
            $this->knowledgeBase . "\n\n" .
            "=== FIN DEL MANUAL DE POLÍTICAS ===\n\n" .
            "=== INSTRUCCIONES DE ANÁLISIS ===\n\n" .
            $baseInstruction . "\n\n" .
            "REGLAS OBLIGATORIAS PARA LA RESPUESTA SUGERIDA:\n" .
            "- Tu 'suggested_response' DEBE basarse ÚNICA Y ESTRICTAMENTE en las políticas, plazos, procedimientos y reglas descritas en el Manual de Políticas anterior.\n" .
            "- NO inventes plazos, porcentajes, condiciones ni procedimientos que no estén explícitamente documentados en el manual.\n" .
            "- Si el correo del cliente trata sobre un tema NO cubierto por el manual, indica en la respuesta sugerida que el caso debe ser escalado a un supervisor para su revisión.\n" .
            "- Cita las secciones relevantes del manual cuando sea apropiado (por ejemplo: 'Según nuestra política de devoluciones...').\n" .
            "- Mantén un tono profesional y empático en la respuesta sugerida.";
    }

    /**
     * Construye ÚNICAMENTE el contexto de la conversación y el correo actual.
     * Ya no contiene instrucciones de sistema.
     */
    private function buildPrompt(string $messageText, array $conversationHistory): string
    {
        $contextBlock = '';
        if (!empty($conversationHistory)) {
            $contextBlock = "Historial de la conversación (mensajes anteriores del mismo ticket):\n";
            foreach ($conversationHistory as $i => $msg) {
                $contextBlock .= "--- Mensaje " . ($i + 1) . " ---\n";
                $contextBlock .= "De: " . ($msg['sender_email'] ?? 'desconocido') . "\n";
                $contextBlock .= $msg['body_text'] . "\n\n";
            }
            $contextBlock .= "--- Nuevo mensaje a analizar ---\n";
        }

        return <<<PROMPT


{$contextBlock}
Correo del cliente a analizar:
{$messageText}
PROMPT;
    }

    /**
     * Parsea la respuesta de Gemini y extrae los campos esperados.
     */
    private function parseResponse(string $response): ?array
    {
        $data = json_decode($response, true);

        if (!$data) {
            error_log('GeminiService: Respuesta JSON inválida en el primer nivel');
            return null;
        }

        $chunks = isset($data[0]) ? $data : [$data];
        $fullText = '';

        foreach ($chunks as $chunk) {
            $parts = $chunk['candidates'][0]['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (isset($part['thought']) && $part['thought'] === true) {
                    continue;
                }
                if (isset($part['text'])) {
                    $fullText .= $part['text'];
                }
            }
        }

        if (empty($fullText)) {
            error_log('GeminiService: No se encontró texto en la respuesta');
            return null;
        }

        $cleanedText = preg_replace('/^```json\s*/i', '', trim($fullText));
        $cleanedText = preg_replace('/\s*```$/', '', $cleanedText);

        $result = json_decode($cleanedText, true);

        if (!$result || !isset($result['suggested_response'])) {
            error_log('GeminiService: Formato de respuesta inesperado - ' . $fullText);
            return null;
        }

        return [
            'category' => $result['category'] ?? 'Otro',
            'sentiment' => $result['sentiment'] ?? 'neutral',
            'suggested_response' => $result['suggested_response']
        ];
    }
}