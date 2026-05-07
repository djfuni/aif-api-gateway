<?php
declare(strict_types=1);
require_once __DIR__ . '/../ai_api_gateway_lib.php';
final class AifGateway {
    public static function models(): void { ai_api_handle_models(); }
    public static function chatCompletions(): void { ai_api_handle_chat_completion(); }
    public static function responses(): void { ai_api_handle_responses(); }
    public static function audioSpeech(): void { ai_api_handle_audio_speech(); }
}
