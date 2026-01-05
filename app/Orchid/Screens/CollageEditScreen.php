<?php

namespace App\Orchid\Screens;

use App\Models\Collage;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Fields\Upload;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Repository;
use Orchid\Support\Facades\Layout;
use Illuminate\Http\Request;
use Orchid\Support\Facades\Toast;

class CollageEditScreen extends Screen
{
    /**
     * Query data.
     *
     * @return array
     */
    public function query(Collage $collage): array
    {
        return [
            'collage' => $collage,
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Шаблон коллажа';
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'Управление шаблоном для ИИ-фото';
    }

    /**
     * Check if we're editing an existing collage.
     *
     * @param Repository $repository
     * @return bool
     */
    private function exists(Repository $repository): bool
    {
        $collage = $repository->getContent('collage');
        return $collage && $collage->exists;
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): array
    {
        return [
            Button::make('Сохранить')
                ->icon('check')
                ->method('save'),

            Button::make('Удалить')
                ->icon('trash')
                ->method('remove'),
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
            Layout::rows([
                Input::make('collage.title')
                    ->title('Название')
                    ->placeholder('Введите название шаблона')
                    ->required(),

                TextArea::make('collage.prompt')
                    ->title('Промт')
                    ->placeholder('Введите промт для генерации коллажа')
                    ->rows(4),

                Upload::make('collage.preview_path')
                    ->title('Превью изображение')
                    ->acceptedTypes('.jpg,.jpeg,.png,.webp')
                    ->storage('public')
                    ->maxFiles(1)
                    ->required(),

                Upload::make('collage.images_for_generation')
                    ->title('Изображения для генерации')
                    ->acceptedTypes('.jpg,.jpeg,.png,.webp')
                    ->storage('public')
                    ->required(),

                Input::make('collage.price')
                    ->title('Цена')
                    ->type('number')
                    ->min(0)
                    ->placeholder('0')
                    ->required(),

                Switcher::make('collage.is_active')
                    ->title('Активный'),
            ])
        ];
    }

    /**
     * Save collage.
     *
     * @param \App\Models\Collage $collage
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Collage $collage, Request $request)
    {
        $data = $request->all();

        // Если это новый коллаж, создаем его
        if (!$collage->exists) {
            $collage = new Collage();
        }

        // Обработка поля preview_path - извлекаем ID из массива, если это массив
        if (isset($data['collage']['preview_path']) && is_array($data['collage']['preview_path'])) {
            $previewPath = $data['collage']['preview_path'];
            // Если массив содержит элементы, берем первый ID
            if (!empty($previewPath) && is_array($previewPath)) {
                $data['collage']['preview_path'] = $previewPath[0] ?? null;
            }
        }

        // Обработка поля images_for_generation - оставляем как массив
        if (isset($data['collage']['images_for_generation']) && !is_array($data['collage']['images_for_generation'])) {
            // Если приходит один элемент, а не массив, создаем массив
            $data['collage']['images_for_generation'] = [$data['collage']['images_for_generation']];
        }

        $collage->fill($data['collage']);
        $collage->save();

        Toast::info('Шаблон сохранен успешно!');

        return redirect()->route('platform.collages');
    }

    /**
     * Remove collage.
     *
     * @param \App\Models\Collage $collage
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function remove(Collage $collage)
    {
        $collage->delete();

        Toast::info('Шаблон удален успешно!');

        return redirect()->route('platform.collages');
    }
}
