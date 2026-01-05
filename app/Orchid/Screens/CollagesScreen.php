<?php

namespace App\Orchid\Screens;

use App\Models\Collage;
use Orchid\Attachment\Models\Attachmentable;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\Upload;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layout;
use Orchid\Support\Facades\Layout as LayoutComponent;
use Orchid\Screen\TD;
use Orchid\Attachment\Models\Attachment;

class CollagesScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(): array
    {
        return [
            'collages' => Collage::paginate(10),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Шаблоны коллажей';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Управление шаблонами для ИИ-фото';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        return [
            Link::make('Создать шаблон')
                ->icon('plus')
                ->route('platform.collage.create'),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): array
    {
        return [
            LayoutComponent::table('collages', [
                TD::make('title', 'Название'),
                TD::make('prompt', 'Подсказка')
                    ->render(function (Collage $collage) {
                        return $collage->prompt;
                    }),
                TD::make('preview_path', 'Путь к превью')
                    ->render(function (Collage $collage) {
                        $value = '';
                        if ($collage->preview_path && is_string($collage->preview_path)) {
                            $attachment = Attachment::find($collage->preview_path);
                            if ($attachment) {
                                $value = '<img src="' . $attachment->url . '" width="30" height="30" alt="Preview" style="object-fit: cover;">';
                            }
                        } elseif (is_array($collage->preview_path) && count($collage->preview_path) > 0) {
                            // Обратная совместимость: если старые данные хранятся как массив, берем первый элемент
                            $attachmentId = $collage->preview_path[0];
                            $attachment = Attachment::find($attachmentId);
                            if ($attachment) {
                                $value = '<img src="' . $attachment->url . '" width="30" height="30" alt="Preview" style="object-fit: cover;">';
                            }
                        }

                        return $value;
                    }),
                TD::make('is_active', 'Активный')
                    ->render(function (Collage $collage) {
                        return $collage->is_active ? 'Да' : 'Нет';
                    }),
                TD::make('price', 'Цена')
                    ->render(function (Collage $collage) {
                        return $collage->price;
                    }),
                TD::make('actions', 'Действия')
                    ->render(function (Collage $collage) {
                        return Button::make('Редактировать')
                            ->icon('pencil')
                            ->method('edit')
                            ->parameters(['collage' => $collage->id])
                            ->canSee(true);
                    }),
            ]),
        ];
    }

    /**
     * Handle edit action.
     *
     * @param \App\Models\Collage $collage
     * @return \Illuminate\Http\RedirectResponse
     */
    public function edit(Collage $collage)
    {
        // Redirect to the edit screen for the selected collage.
        // Pass the collage ID explicitly to satisfy the route parameters.
        return redirect()->route('platform.collage.edit', ['collage' => $collage->id]);
    }
}
