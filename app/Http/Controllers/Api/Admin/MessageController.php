<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EnquiryReply;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Enquiries in the mobile app: list, read, reply by email, mark read/unread, delete. */
class MessageController extends Controller
{
    public function index(Request $request)
    {
        $page = ContactMessage::query()
            ->when($request->input('q'), function (Builder $q, string $term) {
                $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%"));
            })
            ->when($request->input('status') === 'unread', fn (Builder $q) => $q->unread())
            ->when(in_array($request->input('source'), ['popup', 'contact', 'app', 'landing'], true),
                fn (Builder $q) => $q->where('source', $request->input('source')))
            ->withCount('replies')->latest()->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map->toApi(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'unread' => ContactMessage::unread()->count(),
        ]);
    }

    public function show(ContactMessage $message)
    {
        if ($message->isUnread()) {
            $message->forceFill(['read_at' => now()])->save();
        }
        $message->load('replies');

        return response()->json($message->toApi(true));
    }

    public function reply(Request $request, ContactMessage $message)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:2', 'max:10000'],
        ]);

        try {
            $reply = $message->replies()->make($data);
            Mail::to($message->email, $message->name)->send(new EnquiryReply($message, $reply));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'The email could not be sent. Please try again in a moment.'], 502);
        }

        $reply->save();
        $message->forceFill(['read_at' => $message->read_at ?? now()])->save();

        return response()->json($message->load('replies')->toApi(true));
    }

    public function toggleRead(ContactMessage $message)
    {
        $message->forceFill(['read_at' => $message->isUnread() ? now() : null])->save();

        return response()->json(['unread' => $message->isUnread()]);
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return response()->json(['ok' => true]);
    }
}
