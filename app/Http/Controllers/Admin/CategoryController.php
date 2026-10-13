<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Menu categories, edited in the Categories modal on the menu screen. */
class CategoryController extends Controller
{
    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create([
            'code' => $this->uniqueCode($request->input('name')),
            'name' => trim($request->input('name')),
            'icon' => 'bread',
            'is_drink' => false,
            'sort_order' => (int) Category::max('sort_order') + 1,
        ]);

        AuditLogger::record('Added category', $category->name, $request->user());

        return $this->back("Added {$category->name}.");
    }

    public function update(CategoryRequest $request, string $category): RedirectResponse
    {
        $row = $this->find($category);
        $old = $row->name;
        $row->update(['name' => trim($request->input('name'))]);

        if ($old !== $row->name) {
            AuditLogger::record('Edited category', "{$old} → {$row->name}", $request->user());
        }

        return $this->back("Saved {$row->name}.");
    }

    /** A category that still has items can't be deleted: move or remove them first. */
    public function destroy(Request $request, string $category): RedirectResponse
    {
        $row = $this->find($category);

        if ($row->items()->exists()) {
            return redirect()->route('admin.menu-items.index')
                ->withErrors(['category' => "{$row->name} still has items. Move or remove them first."])
                ->withInput(['_modal' => 'categoriesModal']);
        }

        $row->delete();
        AuditLogger::record('Removed category', $row->name, $request->user());

        return $this->back("Removed {$row->name}.");
    }

    private function find(string $code): Category
    {
        return Category::where('code', $code)->first() ?? abort(404);
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('admin.menu-items.index')->with('status', $message);
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $code = $base;

        for ($n = 2; Category::where('code', $code)->exists(); $n++) {
            $code = $base.'-'.$n;
        }

        return $code;
    }
}
