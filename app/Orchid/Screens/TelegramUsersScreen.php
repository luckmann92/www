<?php

namespace App\Orchid\Screens;

use App\Models\TelegramUser;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class TelegramUsersScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [
            'users' => TelegramUser::with('supportTickets')
                ->orderBy('last_interaction_at', 'desc')
                ->paginate(50),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Пользователи Telegram';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Список пользователей, взаимодействовавших с ботом';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        return [];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): array
    {
        return [
            Layout::table('users', [
                TD::make('id', 'ID')
                    ->width('50px'),

                TD::make('telegram_id', 'Telegram ID')
                    ->width('100px'),

                TD::make('full_name', 'Имя')
                    ->render(fn (TelegramUser $user) => $user->full_name),

                TD::make('username', 'Username')
                    ->render(fn (TelegramUser $user) => $user->username ? '@' . $user->username : '-'),

                TD::make('phone', 'Телефон')
                    ->render(fn (TelegramUser $user) => $user->phone ?? '-'),

                TD::make('support_tickets_count', 'Обращений')
                    ->render(fn (TelegramUser $user) => $user->supportTickets->count()),

                TD::make('last_interaction_at', 'Последняя активность')
                    ->render(fn (TelegramUser $user) => $user->last_interaction_at ? $user->last_interaction_at->format('d.m.Y H:i') : '-'),

                TD::make('created_at', 'Дата регистрации')
                    ->render(fn (TelegramUser $user) => $user->created_at->format('d.m.Y H:i')),
            ]),
        ];
    }
}
