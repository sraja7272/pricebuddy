<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Enums\Icons;
use App\Filament\Actions\BaseAction;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Product $record
 */
class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.product-resource.pages.view';

    public function getTitle(): string|Htmlable
    {
        return $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            ProductResource\Actions\AddUrlAction::make(),
            ProductResource\Actions\FetchAction::make(),
            ProductResource\Actions\ShareProductPageAction::make(),
            BaseAction::make('edit_product')->icon(Icons::Edit->value)
                ->label(__('Edit'))
                ->resourceName('product')
                ->resourceUrl('edit', $this->record),
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
