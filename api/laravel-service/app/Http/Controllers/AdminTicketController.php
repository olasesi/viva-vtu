<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['user', 'assignee'])
            ->orderBy('updated_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        $tickets = $query->paginate((int) $request->get('limit', 20));

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::with(['user', 'assignee', 'messages.user'])->find($id);

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
        $ticket = Ticket::find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:5000',
            'status' => 'nullable|in:open,in_progress,awaiting_response,resolved,closed',
            'internal' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $adminId = $request->user()['id'] ?? $request->user('api')['id'] ?? null;

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $adminId,
            'message' => $request->input('message'),
            'is_internal_note' => (bool) $request->input('internal', false),
        ]);

        $ticket->update([
            'status' => $request->input('status', 'in_progress'),
            'assigned_to' => $ticket->assigned_to ?? $adminId,
            'last_reply_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reply sent',
            'data' => $ticket->fresh()->load('messages.user'),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:open,in_progress,awaiting_response,resolved,closed',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticket->update($request->only(['status', 'priority', 'assigned_to']));

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated',
            'data' => $ticket->fresh(),
        ]);
    }
}
