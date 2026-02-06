<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Message;
use Http;

class GrokService {
    public string $api_token;

    public function __construct () { $this->api_token = env('GROK_API'); }

    public function sendMessage ( $user_id , $message ) {
        $messages = Message::query()
                           ->where('user_id' , $user_id)
                           ->orderBy('id')
                           ->take(2)
                           ->get([
                                     'role' ,
                                     'content' ,
                                 ])
                           ->toArray();
        $ai_setting = AiSetting::query()->firstOrCreate([]);
        $messages[] = [
            'role' => 'system' ,
            'content' => $ai_setting->system_content ,
        ];
        $messages[] = [
            'role' => 'user' ,
            'content' => $message ,
        ];

        return Http::withHeaders([
            'Accept-Encoding' => 'gzip',
        ])->post('https://forwarder.concrete-town.top/api/grok/send', [
            'api_token' => $this->api_token,
            'messages' => $messages,
            'max_completion_tokens' => (int) $ai_setting->max_completion_tokens,
        ])->json()['content'];

        return Http::timeout(60)->withHeaders([
                                     'Authorization' => $this->api_token ,
                                 ])
                   ->post('https://api.x.ai/v1/chat/completions' , [
                       'model' => 'grok-3-latest' ,
                       'stream' => false ,
                       'temperature' => 0.7 ,
                       'messages' => $messages ,
                       'max_completion_tokens' => (int)$ai_setting->max_completion_tokens,
                   ])
                   ->json()[ 'choices' ][ 0 ][ 'message' ][ 'content' ];
    }
}
