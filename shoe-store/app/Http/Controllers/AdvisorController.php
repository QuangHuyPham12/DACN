<?php

namespace App\Http\Controllers;

use App\Services\ShoeAdvisor;
use Illuminate\Http\Request;

class AdvisorController extends Controller
{
    public function history(Request $request)
    {
        return response()->json([
            'messages' => $request->session()->get('advisor.messages', []),
            'mode' => config('advisor.api_key') ? 'ai' : 'catalog',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function message(Request $request, ShoeAdvisor $advisor)
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'product_id' => 'nullable|integer|min:1',
        ]);
        $message = trim($data['message']);
        abort_if($message === '', 422, 'Vui lòng nhập câu hỏi.');
        $history = $request->session()->get('advisor.messages', []);
        $result = $advisor->answer($message, $request->session()->get('advisor.preferences', []), $history, $data['product_id'] ?? null);
        $history[] = ['role' => 'user', 'text' => $message];
        $history[] = ['role' => 'assistant', ...$result['answer']];
        $request->session()->put('advisor', [
            'preferences' => $result['preferences'],
            'messages' => array_slice($history, -config('advisor.history_limit')),
        ]);

        return response()->json($result['answer'])->header('Cache-Control', 'private, no-store');
    }

    public function clear(Request $request)
    {
        $request->session()->forget('advisor');

        return response()->json(['cleared' => true])->header('Cache-Control', 'private, no-store');
    }
}
