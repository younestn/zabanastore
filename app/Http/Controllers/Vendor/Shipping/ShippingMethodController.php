<?php

namespace App\Http\Controllers\Vendor\Shipping;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\CategoryShippingCostRepositoryInterface;
use App\Contracts\Repositories\ShippingMethodRepositoryInterface;
use App\Contracts\Repositories\ShippingTypeRepositoryInterface;
use App\Enums\ViewPaths\Vendor\Dashboard;
use App\Enums\ViewPaths\Vendor\ShippingMethod;
use App\Http\Controllers\BaseController;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use App\Http\Requests\Vendor\ShippingMethodRequest;
use App\Services\CategoryShippingCostService;
use App\Services\ShippingMethodService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ShippingMethodController extends BaseController
{
    /**
     * @param ShippingMethodRepositoryInterface $shippingMethodRepo
     * @param ShippingMethodService $shippingMethodService
     * @param CategoryRepositoryInterface $categoryRepo
     * @param CategoryShippingCostRepositoryInterface $categoryShippingCostRepo
     * @param CategoryShippingCostService $categoryShippingCostService
     * @param ShippingTypeRepositoryInterface $shippingTypeRepo
     */
    public function __construct(
        private readonly ShippingMethodRepositoryInterface       $shippingMethodRepo,
        private readonly ShippingMethodService                   $shippingMethodService,
        private readonly CategoryRepositoryInterface             $categoryRepo,
        private readonly CategoryShippingCostRepositoryInterface $categoryShippingCostRepo,
        private readonly CategoryShippingCostService             $categoryShippingCostService,
        private readonly ShippingTypeRepositoryInterface         $shippingTypeRepo,
    )
    {
    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     */
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
       return $this->getShippingMethodsView();
    }
    /**
     * @return View|RedirectResponse
     */
    public function getShippingMethodsView(): View|RedirectResponse
{
    $shippingMethod = getWebConfig(name: 'shipping_method');
    $vendorId = auth('seller')->id();

    $noestShippingCompany = DB::table('vendor_shipping_companies')
        ->where('vendor_id', $vendorId)
        ->whereRaw('LOWER(name) = ?', ['noest'])
        ->first();

    if ($shippingMethod === 'sellerwise_shipping') {
        $allCategoryIds = $this->categoryRepo->getListWhere(filters: ['position' => 0])->pluck('id')->toArray();
        $allCategoryShippingCostArray = $this->categoryShippingCostRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            filters: ['seller_id' => $vendorId],
        )->pluck('category_id')->toArray();

        foreach ($allCategoryIds as $id) {
            if (!in_array($id, $allCategoryShippingCostArray)) {
                $this->categoryShippingCostRepo->add(
                    data: $this->categoryShippingCostService->getAddCategoryWiseShippingCostData(
                        addedBy: 'seller',
                        id: $id
                    )
                );
            }
        }

        $allCategoryShippingCost = $this->categoryShippingCostRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            filters: ['seller_id' => $vendorId],
            relations: ['category']
        );

        $sellerShipping = $this->shippingTypeRepo->getFirstWhere(
            params: ['seller_id' => $vendorId]
        );

        $shippingType = isset($sellerShipping) ? $sellerShipping['shipping_type'] : 'order_wise';

        $shippingMethods = $this->shippingMethodRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            filters: ['creator_id' => $vendorId, 'creator_type' => 'seller'],
            dataLimit: getWebConfig(name: 'pagination_limit')
        );

        return view(
            ShippingMethod::INDEX[VIEW],
            compact('shippingMethods', 'allCategoryShippingCost', 'shippingType', 'noestShippingCompany')
        );
    } else {
        return redirect()->route(Dashboard::INDEX[ROUTE]);
    }
}

    /**
     * @param ShippingMethodRequest $request
     * @return RedirectResponse
     */
    public function add(ShippingMethodRequest $request): RedirectResponse
    {
        $this->shippingMethodRepo->add($this->shippingMethodService->addShippingMethodData(request: $request, addedBy: 'seller'));
        ToastMagic::success(translate('successfully_added'));
        return redirect()->back();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateStatus(Request $request):JsonResponse
    {
        $this->shippingMethodRepo->update(id:$request['id'],data:[ 'status' => $request['status']]);
        return response()->json(['success' => 1,],status:200);
    }

    /**
     * @param string|int $id
     * @return View|RedirectResponse
     */
    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        $shippingMethod = getWebConfig(name: 'shipping_method');
        if ($shippingMethod === 'sellerwise_shipping') {
            $shippingMethod = $this->shippingMethodRepo->getFirstWhere(params: ['id' => $id]);
            return view(ShippingMethod::UPDATE[VIEW], compact('shippingMethod'));
        } else {
            return redirect()->route(Dashboard::INDEX[ROUTE]);
        }
    }

    /**
     * @param ShippingMethodRequest $request
     * @param string|int $id
     * @return RedirectResponse
     */
    public function update(ShippingMethodRequest $request , string|int $id):RedirectResponse
    {
        $this->shippingMethodRepo->update(id: $id, data: $this->shippingMethodService->addShippingMethodData(request: $request, addedBy: 'seller'));
        ToastMagic::success(translate('successfully_updated'));
        return redirect()->route(ShippingMethod::INDEX[ROUTE]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function delete(Request $request):RedirectResponse
    {
        $this->shippingMethodRepo->delete(params: ['id' => $request['id']]);
        return redirect()->back();
    }

    public function updateNoestSettings(Request $request): RedirectResponse
{
    $request->validate([
        'noest_guid' => ['nullable', 'string', 'max:255'],
        'api_token' => ['nullable', 'string', 'max:255'],
    ]);

    if ($request->boolean('status') && (!$request->filled('noest_guid') || !$request->filled('api_token'))) {
        Toastr::error(translate('please_add_noest_guid_and_api_token_before_enabling_noest'));
        return redirect()->back()->withInput();
    }

    $vendorId = auth('seller')->id();

    $noestShippingCompany = DB::table('vendor_shipping_companies')
        ->where('vendor_id', $vendorId)
        ->whereRaw('LOWER(name) = ?', ['noest'])
        ->first();

    $data = [
        'vendor_id' => $vendorId,
        'name' => 'Noest',
        'website' => 'noest-dz.com',
        'noest_guid' => $request->input('noest_guid'),
        'api_token' => $request->input('api_token'),
        'status' => $request->boolean('status') ? 1 : 0,
        'updated_at' => now(),
    ];

    if ($noestShippingCompany) {
        DB::table('vendor_shipping_companies')
            ->where('id', $noestShippingCompany->id)
            ->update($data);
    } else {
        $data['connected_since'] = null;
        $data['created_at'] = now();

        DB::table('vendor_shipping_companies')->insert($data);
    }

    Toastr::success(translate('NOEST_settings_updated_successfully'));
    return redirect()->back();
}

public function testNoestConnection(Request $request): RedirectResponse
{
    $request->validate([
        'noest_guid' => ['required', 'string', 'max:255'],
        'api_token' => ['required', 'string', 'max:255'],
    ]);

    try {
        $response = Http::timeout(15)
            ->acceptJson()
            ->withToken($request->input('api_token'))
            ->get('https://app.noest-dz.com/api/public/get/wilayas', [
                'user_guid' => $request->input('noest_guid'),
            ]);

        $responseData = $response->json();

        $isConnected = $response->successful() && is_array($responseData) && count($responseData) > 0;

        if (!$isConnected) {
            Toastr::error(translate('unable_to_connect_with_NOEST_please_check_GUID_and_API_token'));
            return redirect()->back()
                ->withInput()
                ->with('noest_connection_status', 0);
        }

        $vendorId = auth('seller')->id();

        $noestShippingCompany = DB::table('vendor_shipping_companies')
            ->where('vendor_id', $vendorId)
            ->whereRaw('LOWER(name) = ?', ['noest'])
            ->first();

        $data = [
            'vendor_id' => $vendorId,
            'name' => 'Noest',
            'website' => 'noest-dz.com',
            'noest_guid' => $request->input('noest_guid'),
            'api_token' => $request->input('api_token'),
            'status' => $request->boolean('status') ? 1 : ($noestShippingCompany->status ?? 0),
            'connected_since' => now()->toDateString(),
            'updated_at' => now(),
        ];

        if ($noestShippingCompany) {
            DB::table('vendor_shipping_companies')
                ->where('id', $noestShippingCompany->id)
                ->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('vendor_shipping_companies')->insert($data);
        }

        Toastr::success(translate('NOEST_connection_successful'));
        return redirect()->back()->with('noest_connection_status', 1);

    } catch (\Throwable $exception) {
        Toastr::error(translate('unable_to_connect_with_NOEST_please_check_GUID_and_API_token'));
        return redirect()->back()
            ->withInput()
            ->with('noest_connection_status', 0);
    }
}
}
