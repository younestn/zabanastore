<?php

namespace App\Http\Controllers\Admin\Product;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ExportFileNames\Admin\Category as SubCategoryExport;
use App\Enums\ViewPaths\Admin\SubCategory;
use App\Exports\CategoryListExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Http\Requests\Admin\SubCategoryAddRequest;
use App\Services\CategoryService;
use App\Traits\PaginatorTrait;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SubCategoryController extends BaseController
{
    use PaginatorTrait;

    public function __construct(
        private readonly CategoryRepositoryInterface    $categoryRepo,
        private readonly TranslationRepositoryInterface $translationRepo,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View Index function is the starting point of a controller
     * Index function is the starting point of a controller
     */
    public function index(Request|null $request, string $type = null): View
    {
        $parentCategoryIDs = $request['categories'] ?? [];

        if ($request['sort_by'] == 'latest') {
            $orderBy = ['created_at' => 'desc'];
        } elseif ($request['sort_by'] == 'oldest') {
            $orderBy = ['created_at' => 'asc'];
        } elseif ($request['sort_by'] == 'a-z') {
            $orderBy = ['name' => 'asc'];
        } elseif ($request['sort_by'] == 'z-a') {
            $orderBy = ['name' => 'desc'];
        } else  {
            $orderBy = ['updated_at' => 'desc'];
        }

        $categories = $this->categoryRepo->getListWhereIn(
            orderBy: $orderBy,
            searchValue: $request->get('searchValue'),
            filters: ['position' => 1],
            whereIn: ['parent_id' => $parentCategoryIDs],
            dataLimit: getWebConfig(name: 'pagination_limit'));

        $parentCategories = $this->categoryRepo->getListWhere(
            orderBy: ['name' => 'asc'],
            filters: ['position' => 0],
            dataLimit: 'all');

        $filterParentCategories = $this->categoryRepo->getListWhere(
            orderBy: ['name' => 'asc'],
            filters: ['position' => 0],
            dataLimit: $request['categories'] ? 1000 : 5);

        $languages = getWebConfig(name: 'pnc_language') ?? null;
        $defaultLanguage = $languages[0];

        return view('admin-views.category.sub-category-view', [
            'categories' => $categories,
            'parentCategories' => $parentCategories,
            'filterParentCategories' => $filterParentCategories,
            'languages' => $languages,
            'defaultLanguage' => $defaultLanguage,
        ]);
    }

    public function getUpdateView(string|int $id): View
    {
        $category = $this->categoryRepo->getFirstWhere(params: ['id' => $id], relations: ['translations']);
        $languages = getWebConfig(name: 'pnc_language') ?? null;
        $defaultLanguage = $languages[0];

        $parentCategories = $this->categoryRepo->getListWhere(
            orderBy: ['name' => 'asc'],
            filters: ['position' => 0],
            dataLimit: 'all');

        return view('admin-views.category.category-edit', [
            'category' => $category,
            'languages' => $languages,
            'defaultLanguage' => $defaultLanguage,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function add(SubCategoryAddRequest $request, CategoryService $categoryService): RedirectResponse
    {
        $dataArray = $categoryService->getAddData(request: $request);
        $savedCategory = $this->categoryRepo->add(data: $dataArray);
        $this->translationRepo->add(request: $request, model: 'App\Models\Category', id: $savedCategory->id);
        ToastMagic::success(translate('category_added_successfully'));
        return back();
    }

    public function update(CategoryUpdateRequest $request, CategoryService $categoryService): JsonResponse
    {
        $category = $this->categoryRepo->getFirstWhere(params: ['id' => $request['id']]);
        $dataArray = $categoryService->getUpdateData(request: $request, data: $category);
        $this->categoryRepo->update(id: $request['id'], data: $dataArray);
        $this->translationRepo->update(request: $request, model: 'App\Models\Category', id: $request['id']);

        ToastMagic::success(translate('category_updated_successfully'));
        return response()->json();
    }

    public function delete(Request $request): JsonResponse
    {
        $this->categoryRepo->delete(params: ['id' => $request['id']]);
        return response()->json(['message' => translate('deleted_successfully')]);
    }

    public function getExportList(Request $request): BinaryFileResponse
    {
        $parentCategoryIDs = $request['categories'] ?? [];
        if ($request['sort_by'] == 'latest') {
            $orderBy = ['created_at' => 'desc'];
        } elseif ($request['sort_by'] == 'oldest') {
            $orderBy = ['created_at' => 'asc'];
        } elseif ($request['sort_by'] == 'a-z') {
            $orderBy = ['name' => 'asc'];
        } elseif ($request['sort_by'] == 'z-a') {
            $orderBy = ['name' => 'desc'];
        } else  {
            $orderBy = ['updated_at' => 'desc'];
        }

        $subCategories = $this->categoryRepo->getListWhereIn(
            orderBy: $orderBy,
            searchValue: $request->get('searchValue'),
            filters: ['position' => 1],
            whereIn: ['parent_id' => $parentCategoryIDs],
            dataLimit: 'all');

        $active = $subCategories->where('home_status', 1)->count();
        $inactive = $subCategories->where('home_status', 0)->count();
        return Excel::download(new CategoryListExport([
            'categories' => $subCategories,
            'title' => 'sub_category',
            'search' => $request['searchValue'],
            'active' => $active,
            'inactive' => $inactive,
        ]), SubCategoryExport::SUB_CATEGORY_LIST_XLSX
        );
    }

    public function loadMoreCategories(Request $request): JsonResponse
    {
        $oldCategories = $request['old_categories'] ? json_decode($request['old_categories']) : [];
        $page = $request->input('page', 1);
        $filterParentCategories = $this->categoryRepo->getListWhere(
            orderBy: ['name' => 'asc'],
            filters: ['position' => 0],
            dataLimit: 5,
            offset: $page);

        $visibleLimit = $filterParentCategories->perPage();
        $totalCategories = $filterParentCategories->total();
        $hiddenCount = $totalCategories - ($page * $visibleLimit);

        return response()->json([
            'html' => view('admin-views.category.offcanvas._parent-categories', [
                'filterParentCategories' => $filterParentCategories,
                'oldCategories' => $oldCategories,
            ])->render(),
            'visibleLimit' => $visibleLimit,
            'hiddenCount' => max(0, $hiddenCount),
            'totalCategories' => $totalCategories,
        ]);
    }
}
