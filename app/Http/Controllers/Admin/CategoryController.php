<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Support\DemoData;
use Illuminate\Http\RedirectResponse;

/** Menu categories, edited in the Categories modal on the menu screen. */
class CategoryController extends Controller
{
    public function store(CategoryRequest $request): RedirectResponse
    {
        return $this->back();
    }

    public function update(CategoryRequest $request, string $category): RedirectResponse
    {
        $this->find($category);

        return $this->back();
    }

    /** A category that still has items can't be deleted: move or remove them first. */
    public function destroy(string $category): RedirectResponse
    {
        $found = $this->find($category);

        if (collect(DemoData::items())->contains('category', $category)) {
            return redirect()->route('admin.menu-items.index')
                ->withErrors(['category' => "{$found['name']} still has items. Move or remove them first."])
                ->withInput(['_modal' => 'categoriesModal']);
        }

        return $this->back();
    }

    private function find(string $code): array
    {
        return collect(DemoData::categories())->firstWhere('code', $code) ?? abort(404);
    }

    private function back(): RedirectResponse
    {
        return $this->demoSaved('admin.menu-items.index');
    }
}
