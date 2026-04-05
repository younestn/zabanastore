<?php

namespace App\Http\Controllers\Admin;
use App\Models\Chatting as ChattingModel;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Contracts\Repositories\ChattingRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\DeliveryManRepositoryInterface;
use App\Contracts\Repositories\ShopRepositoryInterface;
use App\Enums\ViewPaths\Admin\Chatting;
use App\Events\ChattingEvent;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\ChattingRequest;
use App\Services\ChattingService;
use App\Traits\PushNotificationTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ChattingController extends BaseController
{
    use PushNotificationTrait;

    /**
     * @param ChattingRepositoryInterface $chattingRepo
     * @param ShopRepositoryInterface $shopRepo
     * @param ChattingService $chattingService
     * @param DeliveryManRepositoryInterface $deliveryManRepo
     * @param CustomerRepositoryInterface $customerRepo
     */
    public function __construct(
    private readonly ChattingRepositoryInterface    $chattingRepo,
    private readonly ShopRepositoryInterface        $shopRepo,
    private readonly ChattingService                $chattingService,
    private readonly DeliveryManRepositoryInterface $deliveryManRepo,
    private readonly CustomerRepositoryInterface    $customerRepo,
    private readonly VendorRepositoryInterface      $vendorRepo,
)
{
}

public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|callable|RedirectResponse|JsonResponse|null
{
    return $this->getListView(type: $type);
}

    /**
     * @param Request|null $request
     * @param string|array|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     */
public function getListView(string $type = null): View
{
    $shop = null;
    $adminId = 0;

    if ($type == 'delivery-man') {
        $allChattingUsers = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['admin_id' => $adminId],
            whereNotNull: ['delivery_man_id', 'admin_id'],
            relations: ['deliveryMan'],
            dataLimit: 'all'
        )->unique('delivery_man_id');

        $requestedDeliveryManId = request()->get('delivery_man_id');
        $requestedDeliveryMan = null;

        if ($requestedDeliveryManId) {
            $requestedDeliveryMan = $this->deliveryManRepo->getFirstWhere(params: [
                'id' => $requestedDeliveryManId,
            ]);
        }

        if (count($allChattingUsers) > 0 || $requestedDeliveryMan) {
            $lastChatUser = $requestedDeliveryMan ?: $allChattingUsers[0]->deliveryMan;

            if ($lastChatUser) {
                $this->chattingRepo->updateAllWhere(
                    params: ['admin_id' => $adminId, 'delivery_man_id' => $lastChatUser['id']],
                    data: ['seen_by_admin' => 1]
                );
            }

            $deliveryMenUnreadMessagesQueryParams = [
                'admin_id' => $adminId,
                'usersColumn' => 'delivery_man_id',
                'filteredByColumn' => 'seen_by_admin',
                'notificationReceiver' => 'admin',
            ];

            $countUnreadMessages = $this->chattingRepo->countUnreadMessages(data: $deliveryMenUnreadMessagesQueryParams);

            $chattingMessages = $this->chattingRepo->getListWhereNotNull(
                orderBy: ['id' => 'DESC'],
                filters: ['admin_id' => $adminId, 'delivery_man_id' => $lastChatUser?->id],
                whereNotNull: ['delivery_man_id', 'admin_id'],
                relations: ['deliveryMan'],
                dataLimit: 'all'
            );

            return view('admin-views.chatting.index', [
                'userType' => $type,
                'allChattingUsers' => $allChattingUsers,
                'lastChatUser' => $lastChatUser,
                'chattingMessages' => $chattingMessages,
                'countUnreadMessages' => $countUnreadMessages
            ]);
        }
    } elseif ($type == 'customer') {
        $allChattingUsers = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['admin_id' => $adminId],
            whereNotNull: ['user_id', 'admin_id'],
            relations: ['customer'],
            dataLimit: 'all'
        )->unique('user_id');

        $requestedCustomerId = request()->get('customer_id');
        $requestedCustomer = null;

        if ($requestedCustomerId) {
            $requestedCustomer = $this->customerRepo->getFirstWhere(params: [
                'id' => $requestedCustomerId,
            ]);
        }

        if (count($allChattingUsers) > 0 || $requestedCustomer) {
            $lastChatUser = $requestedCustomer ?: $allChattingUsers[0]->customer;

            if ($lastChatUser) {
                $this->chattingRepo->updateAllWhere(
                    params: ['admin_id' => $adminId, 'user_id' => $lastChatUser['id']],
                    data: ['seen_by_admin' => 1]
                );
            }

            $customersUnreadMessagesQueryParams = [
                'admin_id' => $adminId,
                'usersColumn' => 'user_id',
                'filteredByColumn' => 'seen_by_admin',
                'notificationReceiver' => 'admin',
            ];

            $countUnreadMessages = $this->chattingRepo->countUnreadMessages(data: $customersUnreadMessagesQueryParams);

            $chattingMessages = $this->chattingRepo->getListWhereNotNull(
                orderBy: ['id' => 'DESC'],
                filters: ['admin_id' => $adminId, 'user_id' => $lastChatUser?->id],
                whereNotNull: ['user_id', 'admin_id'],
                relations: ['customer'],
                dataLimit: 'all'
            );

            return view('admin-views.chatting.index', [
                'userType' => $type,
                'allChattingUsers' => $allChattingUsers,
                'lastChatUser' => $lastChatUser,
                'chattingMessages' => $chattingMessages,
                'countUnreadMessages' => $countUnreadMessages
            ]);
        }
    } elseif ($type == 'vendor') {
        $allChattingUsers = ChattingModel::query()
    ->with(['seller.shop'])
    ->where('admin_id', $adminId)
    ->whereNotNull('seller_id')
    ->whereIn('id', function ($query) use ($adminId) {
        $query->from('chattings')
            ->selectRaw('MAX(id)')
            ->where('admin_id', $adminId)
            ->whereNotNull('seller_id')
            ->groupBy('seller_id');
    })
    ->orderByDesc('id')
    ->get();

        $requestedVendorId = request()->get('vendor_id');
        $requestedVendor = null;

        if ($requestedVendorId) {
            $requestedVendor = $this->vendorRepo->getFirstWhere(params: [
                'id' => $requestedVendorId,
                'status' => 'approved',
            ]);
        }

        if (count($allChattingUsers) > 0 || $requestedVendor) {
            $lastChatUser = $requestedVendor ?: $allChattingUsers[0]->seller;

            if ($lastChatUser) {
                $this->chattingRepo->updateAllWhere(
                    params: ['admin_id' => $adminId, 'seller_id' => $lastChatUser['id']],
                    data: ['seen_by_admin' => 1]
                );
            }

            $vendorsUnreadMessagesQueryParams = [
                'admin_id' => $adminId,
                'usersColumn' => 'seller_id',
                'filteredByColumn' => 'seen_by_admin',
                'notificationReceiver' => 'admin',
            ];

            $countUnreadMessages = $this->chattingRepo->countUnreadMessages(data: $vendorsUnreadMessagesQueryParams);

            $chattingMessages = $this->chattingRepo->getListWhereNotNull(
                orderBy: ['id' => 'DESC'],
                filters: ['admin_id' => $adminId, 'seller_id' => $lastChatUser?->id],
                whereNotNull: ['seller_id', 'admin_id'],
                relations: ['seller.shop'],
                dataLimit: 'all'
            );

            return view('admin-views.chatting.index', [
                'userType' => $type,
                'allChattingUsers' => $allChattingUsers,
                'lastChatUser' => $lastChatUser,
                'chattingMessages' => $chattingMessages,
                'countUnreadMessages' => $countUnreadMessages
            ]);
        }
    }

    return view('admin-views.chatting.index', compact('shop'));
}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getMessageByUser(Request $request): JsonResponse
{
    $adminId = 0;
    $data = [];

    if ($request->has(key: 'delivery_man_id')) {
        $getUser = $this->deliveryManRepo->getFirstWhere(params: ['id' => $request['delivery_man_id']]);

        if (!$getUser) {
            return response()->json(['message' => 'Delivery man not found'], 404);
        }

        $this->chattingRepo->updateAllWhere(
            params: ['admin_id' => $adminId, 'delivery_man_id' => $request['delivery_man_id']],
            data: ['seen_by_admin' => 1]
        );

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['admin_id' => $adminId, 'delivery_man_id' => $request['delivery_man_id']],
            whereNotNull: ['delivery_man_id', 'admin_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $getUser, message: $chattingMessages, type: 'delivery_man');
    } elseif ($request->has(key: 'user_id')) {
        $getUser = $this->customerRepo->getFirstWhere(params: ['id' => $request['user_id']]);

        if (!$getUser) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $this->chattingRepo->updateAllWhere(
            params: ['admin_id' => $adminId, 'user_id' => $request['user_id']],
            data: ['seen_by_admin' => 1]
        );

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['admin_id' => $adminId, 'user_id' => $request['user_id']],
            whereNotNull: ['user_id', 'admin_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $getUser, message: $chattingMessages, type: 'customer');
    } elseif ($request->has(key: 'seller_id')) {
        $getUser = $this->vendorRepo->getFirstWhere(params: ['id' => $request['seller_id']]);

        if (!$getUser) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }

        $this->chattingRepo->updateAllWhere(
            params: ['admin_id' => $adminId, 'seller_id' => $request['seller_id']],
            data: ['seen_by_admin' => 1]
        );

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['admin_id' => $adminId, 'seller_id' => $request['seller_id']],
            whereNotNull: ['seller_id', 'admin_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $getUser, message: $chattingMessages, type: 'vendor');
    }

    return response()->json($data);
}
    /**
     * @param ChattingRequest $request
     * @return JsonResponse
     */
    public function addAdminMessage(ChattingRequest $request): JsonResponse
{
    $data = [];
    $shop = [
        'name' => getInHouseShopConfig(key: 'name')
    ];

    $messageForm = (object)[
        'f_name' => 'admin',
        'shop' => (object)$shop,
    ];

    if ($request->has(key: 'delivery_man_id')) {
        $this->chattingRepo->add(
            data: $this->chattingService->addChattingData(
                request: $request,
                type: 'delivery-man',
            )
        );

        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['id' => $request['delivery_man_id']]);

        if ($deliveryMan) {
            event(new ChattingEvent(key: 'message_from_admin', type: 'delivery_man', userData: $deliveryMan, messageForm: $messageForm));
        }

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['admin_id' => 0, 'delivery_man_id' => $request['delivery_man_id']],
            whereNotNull: ['delivery_man_id', 'admin_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $deliveryMan, message: $chattingMessages, type: 'delivery_man');
    } elseif ($request->has(key: 'user_id')) {
        $this->chattingRepo->add(
            data: $this->chattingService->addChattingData(
                request: $request,
                type: 'customer',
            )
        );

        $customer = $this->customerRepo->getFirstWhere(params: ['id' => $request['user_id']]);

        if ($customer) {
            event(new ChattingEvent(key: 'message_from_admin', type: 'customer', userData: $customer, messageForm: $messageForm));
        }

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['admin_id' => 0, 'user_id' => $request['user_id']],
            whereNotNull: ['user_id', 'admin_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $customer, message: $chattingMessages, type: 'customer');
    } elseif ($request->has(key: 'seller_id')) {
        $vendor = $this->vendorRepo->getFirstWhere(params: ['id' => $request['seller_id']]);

        if (!$vendor) {
            return response()->json(['message' => 'Vendor not found'], 404);
        }

        $vendorShop = $this->shopRepo->getFirstWhere(params: ['seller_id' => $request['seller_id']]);

        $this->chattingRepo->add(
            data: $this->chattingService->getVendorChattingDataFromAdmin(
                request: $request,
                shopId: $vendorShop?->id ?? 0,
                vendorId: $request['seller_id']
            )
        );

        event(new ChattingEvent(key: 'message_from_admin', type: 'seller', userData: $vendor, messageForm: $messageForm));

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['admin_id' => 0, 'seller_id' => $request['seller_id']],
            whereNotNull: ['seller_id', 'admin_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $vendor, message: $chattingMessages, type: 'vendor');
    }

    return response()->json($data);
}

    /**
     * @param string $tableName
     * @param string $orderBy
     * @param string|int|null $id
     * @return Collection
     */
    protected function getChatList(string $tableName, string $orderBy, string|int $id = null): Collection
    {
        $adminId = 0;
        $columnName = $tableName == 'users' ? 'user_id' : 'delivery_man_id';
        $filters = isset($id) ? ['chattings.admin_id' => $adminId, $columnName => $id] : ['chattings.admin_id' => $adminId];
        return $this->chattingRepo->getListBySelectWhere(
            joinColumn: [$tableName, $tableName . '.id', '=', 'chattings.' . $columnName],
            select: ['chattings.*', $tableName . '.f_name', $tableName . '.l_name', $tableName . '.image', $tableName . '.country_code', $tableName . '.phone'],
            filters: $filters,
            orderBy: ['chattings.id' => $orderBy],
        );
    }

    /**
     * @param object $user
     * @param object $message
     * @param string $type
     * @return array
     */
    protected function getRenderMessagesView(object $user, object $message, string $type): array
{
    if ($type == 'customer') {
        $userData = [
            'name' => $user['f_name'] . ' ' . $user['l_name'],
            'phone' => $user['country_code'] . $user['phone'],
            'detailsRoute' => route('admin.customer.view', $user['id']),
            'image' => getStorageImages(path: $user->image_full_url, type: 'backend-profile'),
        ];
    } elseif ($type == 'vendor') {
        $userData = [
            'name' => trim(($user['f_name'] ?? '') . ' ' . ($user['l_name'] ?? '')),
            'phone' => trim(($user['country_code'] ?? '') . ($user['phone'] ?? '')),
            'detailsRoute' => route('admin.vendors.view', $user['id']),
            'image' => !empty($user->image_full_url)
                ? getStorageImages(path: $user->image_full_url, type: 'backend-profile')
                : getStorageImages(path: getWebConfig(name: 'company_fav_icon'), type: 'backend-logo'),
        ];
    } else {
        $userData = [
            'name' => $user['f_name'] . ' ' . $user['l_name'],
            'phone' => $user['country_code'] . $user['phone'],
            'detailsRoute' => '#',
            'image' => getStorageImages(path: $user->image_full_url, type: 'backend-profile'),
        ];
    }

    return [
        'userData' => $userData,
        'chattingMessages' => view('admin-views.chatting.messages', [
            'lastChatUser' => $user,
            'userType' => $type,
            'chattingMessages' => $message
        ])->render(),
    ];
}
    public function getNewNotification(): JsonResponse
{
    $latestChat = $this->chattingRepo->getListWhereNotNull(
        orderBy: ['id' => 'DESC'],
        filters: [
            'admin_id' => 0,
            'seen_by_admin' => 0,
            'notification_receiver' => 'admin',
            'seen_notification' => 0
        ],
        whereNotNull: ['admin_id'],
        dataLimit: 'all'
    )->first();

    $chatting = $this->chattingRepo->getListWhereNotNull(
        filters: [
            'admin_id' => 0,
            'seen_by_admin' => 0,
            'notification_receiver' => 'admin',
            'seen_notification' => 0
        ],
        whereNotNull: ['admin_id'],
    )->count();

    $this->chattingRepo->updateListWhereNotNull(
        filters: [
            'admin_id' => 0,
            'seen_by_admin' => 0,
            'notification_receiver' => 'admin',
            'seen_notification' => 0
        ],
        whereNotNull: ['admin_id'],
        data: ['seen_notification' => 1]
    );

    return response()->json([
        'newMessagesExist' => $chatting,
        'message' => $chatting > 1 ? $chatting . ' ' . translate('New_Message') : translate('New_Message'),
        'url' => $this->buildAdminMessageNotificationUrl($latestChat),
    ]);
}
private function buildAdminMessageNotificationUrl(object|null $latestChat): string
{
    if ($latestChat) {
        if (!empty($latestChat->seller_id)) {
            return route('admin.messages.index', [
                'type' => 'vendor',
                'vendor_id' => (int)$latestChat->seller_id,
            ]);
        }

        if (!empty($latestChat->delivery_man_id)) {
            return route('admin.messages.index', [
                'type' => 'delivery-man',
                'delivery_man_id' => (int)$latestChat->delivery_man_id,
            ]);
        }

        if (!empty($latestChat->user_id)) {
            return route('admin.messages.index', [
                'type' => 'customer',
                'customer_id' => (int)$latestChat->user_id,
            ]);
        }
    }

    return route('admin.messages.index', ['type' => 'customer']);
}
}
