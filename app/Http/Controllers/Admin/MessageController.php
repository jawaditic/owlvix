<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EnquiryReply;
use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $messages = $this->filtered($request)->withCount('replies')->latest()->paginate(20)->withQueryString();

        $stats = [
            'total' => ContactMessage::count(),
            'unread' => ContactMessage::unread()->count(),
            'week' => ContactMessage::where('created_at', '>=', now()->subDays(7))->count(),
            'popup' => ContactMessage::where('source', 'popup')->count(),
        ];

        $services = ContactMessage::query()
            ->selectRaw('service, count(*) as total')
            ->whereNotNull('service')
            ->groupBy('service')
            ->orderByDesc('total')
            ->pluck('total', 'service');

        return view('admin.messages.index', compact('messages', 'stats', 'services'));
    }

    public function show(ContactMessage $message)
    {
        if ($message->isUnread()) {
            $message->forceFill(['read_at' => now()])->save();
        }

        $message->load('replies');

        return view('admin.messages.show', compact('message'));
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

            return back()->withInput()->withErrors(['body' => 'The email could not be sent. Please try again in a moment.']);
        }

        $reply->save();
        $message->forceFill(['read_at' => $message->read_at ?? now()])->save();

        return redirect()->route('admin.messages.show', $message)->with('status', 'Reply sent to '.$message->email.'.');
    }

    public function toggleRead(ContactMessage $message)
    {
        $message->forceFill(['read_at' => $message->isUnread() ? now() : null])->save();

        return back();
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return redirect()->route('admin.messages.index')->with('status', 'Message deleted.');
    }

    public function export(Request $request)
    {
        $rows = $this->filtered($request)->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 mark, so Excel shows accented names correctly
            // Visitors typed these: a cell starting with = + - @ would run as a formula in Excel or Sheets
            $cell = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
            fputcsv($out, ['Date', 'Name', 'Email', 'Phone', 'Service', 'Budget', 'Source', 'Status', 'Message']);
            foreach ($rows as $m) {
                fputcsv($out, array_map($cell, [
                    $m->created_at->format('Y-m-d H:i'), $m->name, $m->email, $m->phone, $m->service,
                    $m->budget, $m->source, $m->isUnread() ? 'New' : 'Read', $m->messageText(),
                ]));
            }
            fclose($out);
        }, 'owlvix-messages-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request): Builder
    {
        return ContactMessage::query()
            ->when(is_string($request->input('q')) ? trim($request->input('q')) : null, function (Builder $q, string $term) {
                $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%"));
            })
            ->when($request->input('status') === 'unread', fn (Builder $q) => $q->unread())
            ->when(in_array($request->input('source'), ['popup', 'contact', 'app', 'landing', 'seo-checker', 'ai-audit'], true),
                fn (Builder $q) => $q->where('source', $request->input('source')));
    }
}
