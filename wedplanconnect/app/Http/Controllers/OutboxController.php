<?php

namespace App\Http\Controllers;

use App\Models\OutboxMessage;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Test-mode email outbox for staff (admin and planners). Available only while emails
 * are written to the log instead of being delivered, e.g. during demos.
 */
class OutboxController extends Controller
{
    public function index(): View
    {
        abort_unless(OutboxMessage::enabled(), 404);

        return view('outbox.index', [
            'messages' => OutboxMessage::latest('id')->paginate(20),
        ]);
    }

    /** Raw email body, shown in a sandboxed frame. */
    public function show(OutboxMessage $message): Response
    {
        abort_unless(OutboxMessage::enabled(), 404);

        return response((string) $message->html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:",
        ]);
    }
}
