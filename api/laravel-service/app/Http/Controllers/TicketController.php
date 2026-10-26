<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $query = Ticket::with(['messages' => fn ($q) => $q->latest()->limit(1), 'assignee'])
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $tickets = $query->paginate((int) $request->get('limit', 10));

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'category' => 'required|in:transaction,wallet,purchase,account,technical,other',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket = Ticket::create([
            'user_id' => $userId,
            'ticket_number' => Ticket::generateTicketNumber(),
            'subject' => $request->input('subject'),
            'category' => $request->input('category'),
            'priority' => $request->input('priority', 'medium'),
            'status' => 'open',
            'last_reply_at' => now(),
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $request->input('message'),
            'is_internal_note' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'data' => $ticket->load('messages'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $ticket = Ticket::with(['messages.user', 'assignee'])
            ->where('user_id', $userId)
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        $ticket = Ticket::where('user_id', $userId)->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        if (in_array($ticket->status, ['closed', 'resolved'], true)) {
            return response()->json(['success' => false, 'message' => 'Cannot reply to a closed/resolved ticket'], 422);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $request->input('message'),
            'is_internal_note' => false,
        ]);

        $ticket->update([
            'status' => 'awaiting_response',
            'last_reply_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reply sent',
            'data' => $ticket->fresh()->load('messages.user'),
        ]);
    }
}
