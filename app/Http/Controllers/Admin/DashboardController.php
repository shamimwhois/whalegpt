<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * The admin dashboard.
     *
     * Every figure is read live from the application's own tables rather than
     * mocked, so the panel reflects what the install is actually doing: the
     * headline counts, a seven-day delta, recent users and conversations, and
     * the install's own configuration state.
     */
    public function __invoke(): View
    {
        $since = Carbon::now()->subDays(7);

        return view('pages.admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'usersNew' => User::where('created_at', '>=', $since)->count(),
                'conversations' => Conversation::count(),
                'conversationsNew' => Conversation::where('created_at', '>=', $since)->count(),
                'messages' => ChatMessage::count(),
                'projects' => Project::count(),
            ],
            'recentUsers' => User::latest()->limit(6)->get(),
            'recentConversations' => Conversation::with('user')
                ->withCount('messages')
                ->latest('updated_at')
                ->limit(8)
                ->get(),
            'status' => [
                'environment' => config('app.env'),
                'debug' => (bool) config('app.debug'),
                'authRequired' => (bool) config('whale.auth.required', false),
                'admins' => count(config('admin.emails', [])),
                'sttEngine' => config('whale.speech.engine'),
                'database' => config('database.default'),
            ],
        ]);
    }
}
