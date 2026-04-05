<?php

namespace App\Http\Controllers\Vendor;
use App\Models\Admin;
use App\Models\Seller;
use App\Contracts\Repositories\ChattingRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\DeliveryManRepositoryInterface;
use App\Contracts\Repositories\ShopRepositoryInterface;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Enums\ViewPaths\Vendor\Chatting;
use App\Events\ChattingEvent;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Vendor\ChattingRequest;
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
     * @param VendorRepositoryInterface $vendorRepo
     * @param DeliveryManRepositoryInterface $deliveryManRepo
     * @param CustomerRepositoryInterface $customerRepo
     */
    public function __construct(
        private readonly ChattingRepositoryInterface    $chattingRepo,
        private readonly ShopRepositoryInterface        $shopRepo,
        private readonly ChattingService                $chattingService,
        private readonly VendorRepositoryInterface      $vendorRepo,
        private readonly DeliveryManRepositoryInterface $deliveryManRepo,
        private readonly CustomerRepositoryInterface    $customerRepo,
    )
    {
    }


    /**
     * @param Request|null $request
     * @param string|array|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     */
    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|callable|RedirectResponse|JsonResponse|null
{
    return $this->getListView(type: $type);
}
    /**
     * @param string|array $type
     * @return View
     */
    public function getListView(string $type = null): View
{
    $shop = $this->shopRepo->getFirstWhere(params: ['seller_id' => auth('seller')->id()]);
    $vendorId = auth('seller')->id();

    if ($type == 'delivery-man') {
        $allChattingUsers = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['seller_id' => $vendorId],
            whereNotNull: ['delivery_man_id', 'seller_id'],
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
                    params: ['seller_id' => $vendorId, 'delivery_man_id' => $lastChatUser['id']],
                    data: ['seen_by_seller' => 1]
                );
            }

            $deliveryMenUnreadMessagesQueryParams = [
                'seller_id' => $vendorId,
                'usersColumn' => 'delivery_man_id',
                'filteredByColumn' => 'seen_by_seller',
                'notificationReceiver' => 'seller',
            ];

            $countUnreadMessages = $this->chattingRepo->countUnreadMessages(data: $deliveryMenUnreadMessagesQueryParams);

            $chattingMessages = $this->chattingRepo->getListWhereNotNull(
                orderBy: ['id' => 'DESC'],
                filters: ['seller_id' => $vendorId, 'delivery_man_id' => $lastChatUser?->id],
                whereNotNull: ['delivery_man_id', 'seller_id'],
                relations: ['deliveryMan'],
                dataLimit: 'all'
            );

            return view(Chatting::INDEX[VIEW], [
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
            filters: ['seller_id' => $vendorId],
            whereNotNull: ['user_id', 'seller_id'],
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
                    params: ['seller_id' => $vendorId, 'user_id' => $lastChatUser['id']],
                    data: ['seen_by_seller' => 1]
                );
            }

            $customersUnreadMessagesQueryParams = [
                'seller_id' => $vendorId,
                'usersColumn' => 'user_id',
                'filteredByColumn' => 'seen_by_seller',
                'notificationReceiver' => 'seller',
            ];

            $countUnreadMessages = $this->chattingRepo->countUnreadMessages(data: $customersUnreadMessagesQueryParams);

            $chattingMessages = $this->chattingRepo->getListWhereNotNull(
                orderBy: ['id' => 'DESC'],
                filters: ['seller_id' => $vendorId, 'user_id' => $lastChatUser?->id],
                whereNotNull: ['user_id', 'seller_id'],
                relations: ['customer'],
                dataLimit: 'all'
            );

            return view(Chatting::INDEX[VIEW], [
                'userType' => $type,
                'allChattingUsers' => $allChattingUsers,
                'lastChatUser' => $lastChatUser,
                'chattingMessages' => $chattingMessages,
                'countUnreadMessages' => $countUnreadMessages
            ]);
        }
    } elseif ($type == 'admin') {
        $allChattingUsers = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['seller_id' => $vendorId, 'admin_id' => 0],
            whereNotNull: ['seller_id', 'admin_id'],
            dataLimit: 'all'
        )->unique('admin_id');

        if (count($allChattingUsers) > 0) {
            $lastChatUser = $this->getAdminChatUser();

            $this->chattingRepo->updateAllWhere(
                params: ['seller_id' => $vendorId, 'admin_id' => 0],
                data: ['seen_by_seller' => 1]
            );

            $adminUnreadMessagesQueryParams = [
                'seller_id' => $vendorId,
                'usersColumn' => 'admin_id',
                'filteredByColumn' => 'seen_by_seller',
                'notificationReceiver' => 'seller',
            ];

            $countUnreadMessages = $this->chattingRepo->countUnreadMessages(data: $adminUnreadMessagesQueryParams);

            $chattingMessages = $this->chattingRepo->getListWhereNotNull(
                orderBy: ['id' => 'DESC'],
                filters: ['seller_id' => $vendorId, 'admin_id' => 0],
                whereNotNull: ['seller_id', 'admin_id'],
                dataLimit: 'all'
            );

            return view(Chatting::INDEX[VIEW], [
                'userType' => $type,
                'allChattingUsers' => $allChattingUsers,
                'lastChatUser' => $lastChatUser,
                'chattingMessages' => $chattingMessages,
                'countUnreadMessages' => $countUnreadMessages
            ]);
        }
    }

    return view(Chatting::INDEX[VIEW], compact('shop'));
}

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getMessageByUser(Request $request): JsonResponse
{
    $vendorId = auth('seller')->id();
    $data = [];

    if ($request->has(key: 'delivery_man_id')) {
        $getUser = $this->deliveryManRepo->getFirstWhere(params: ['id' => $request['delivery_man_id']]);

        if (!$getUser) {
            return response()->json(['message' => 'Delivery man not found'], 404);
        }

        $this->chattingRepo->updateAllWhere(
            params: ['seller_id' => $vendorId, 'delivery_man_id' => $request['delivery_man_id']],
            data: ['seen_by_seller' => 1]
        );

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['seller_id' => $vendorId, 'delivery_man_id' => $request['delivery_man_id']],
            whereNotNull: ['delivery_man_id', 'seller_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $getUser, message: $chattingMessages, type: 'delivery_man');
    } elseif ($request->has(key: 'user_id')) {
        $getUser = $this->customerRepo->getFirstWhere(params: ['id' => $request['user_id']]);

        if (!$getUser) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $this->chattingRepo->updateAllWhere(
            params: ['seller_id' => $vendorId, 'user_id' => $request['user_id']],
            data: ['seen_by_seller' => 1]
        );

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['seller_id' => $vendorId, 'user_id' => $request['user_id']],
            whereNotNull: ['user_id', 'seller_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $getUser, message: $chattingMessages, type: 'customer');
    } elseif ($request->has(key: 'admin_id')) {
        $getUser = $this->getAdminChatUser();

        $this->chattingRepo->updateAllWhere(
            params: ['seller_id' => $vendorId, 'admin_id' => 0],
            data: ['seen_by_seller' => 1]
        );

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['seller_id' => $vendorId, 'admin_id' => 0],
            whereNotNull: ['seller_id', 'admin_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $getUser, message: $chattingMessages, type: 'admin');
    }

    return response()->json($data);
}
    /**
     * @param ChattingRequest $request
     * @return JsonResponse
     */
public function addVendorMessage(ChattingRequest $request): JsonResponse
{
    $data = [];
    $vendor = $this->vendorRepo->getFirstWhere(params: ['id' => auth('seller')->id()]);
    $shop = $this->shopRepo->getFirstWhere(params: ['seller_id' => auth('seller')->id()]);

    if (!$vendor) {
        return response()->json(['message' => 'Vendor not found'], 404);
    }

    if ($request->has(key: 'delivery_man_id')) {
        $this->chattingRepo->add(
            data: $this->chattingService->getDeliveryManChattingData(
                request: $request,
                shopId: $shop['id'],
                vendorId: $vendor['id']
            )
        );

        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['id' => $request['delivery_man_id']]);

        if ($deliveryMan) {
            event(new ChattingEvent(key: 'message_from_seller', type: 'delivery_man', userData: $deliveryMan, messageForm: $vendor));
        }

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['seller_id' => $vendor['id'], 'delivery_man_id' => $request['delivery_man_id']],
            whereNotNull: ['delivery_man_id', 'seller_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $deliveryMan, message: $chattingMessages, type: 'delivery_man');
    } elseif ($request->has(key: 'user_id')) {
        $this->chattingRepo->add(
            data: $this->chattingService->getCustomerChattingData(
                request: $request,
                shopId: $shop['id'],
                vendorId: $vendor['id']
            )
        );

        $customer = $this->customerRepo->getFirstWhere(params: ['id' => $request['user_id']]);

        if ($customer) {
            event(new ChattingEvent(key: 'message_from_seller', type: 'customer', userData: $customer, messageForm: $vendor));
        }

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['seller_id' => $vendor['id'], 'user_id' => $request['user_id']],
            whereNotNull: ['user_id', 'seller_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $customer, message: $chattingMessages, type: 'customer');
    } elseif ($request->has(key: 'admin_id')) {
        $this->chattingRepo->add(
            data: $this->chattingService->getAdminChattingDataFromVendor(
                request: $request,
                shopId: $shop['id'],
                vendorId: $vendor['id']
            )
        );

        $admin = new Admin();
        $admin->id = 0;
        $admin->name = translate('admin');

        event(new ChattingEvent(key: 'message_from_seller', type: 'admin', userData: $admin, messageForm: $vendor));

        $chattingMessages = $this->chattingRepo->getListWhereNotNull(
            orderBy: ['id' => 'DESC'],
            filters: ['seller_id' => $vendor['id'], 'admin_id' => 0],
            whereNotNull: ['seller_id', 'admin_id'],
            dataLimit: 'all'
        );

        $data = self::getRenderMessagesView(user: $this->getAdminChatUser(), message: $chattingMessages, type: 'admin');
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
        $vendorId = auth('seller')->id();
        $columnName = $tableName == 'users' ? 'user_id' : 'delivery_man_id';
        $filters = isset($id) ? ['chattings.seller_id' => $vendorId, $columnName => $id] : ['chattings.seller_id' => $vendorId];
        return $this->chattingRepo->getListBySelectWhere(
            joinColumn: [$tableName, $tableName . '.id', '=', 'chattings.' . $columnName],
            select: ['chattings.*', $tableName . '.f_name', $tableName . '.l_name', $tableName . '.image'],
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
    if ($type === 'admin') {
        $userData = [
            'name' => translate('admin'),
            'phone' => '',
            'detailsRoute' => '#',
            'image' => getStorageImages(path: getWebConfig(name: 'company_fav_icon'), type: 'backend-logo'),
        ];
    } else {
        $userData = [
            'name' => trim(($user['f_name'] ?? '') . ' ' . ($user['l_name'] ?? '')),
            'phone' => trim(($user['country_code'] ?? '') . ($user['phone'] ?? '')),
            'detailsRoute' => $type == 'customer'
                ? route('vendor.orders.list', ['status' => 'all', 'filter' => 'all', 'customer_id' => $user['id']])
                : '#',
            'image' => getStorageImages(path: $user->image_full_url, type: 'backend-profile'),
        ];
    }

    return [
        'userData' => $userData,
        'chattingMessages' => view('vendor-views.chatting.messages', [
            'lastChatUser' => $user,
            'userType' => $type,
            'chattingMessages' => $message
        ])->render(),
    ];
}

    public function getNewNotification(): JsonResponse
{
    $vendorId = auth('seller')->id();

    $latestChat = $this->chattingRepo->getListWhereNotNull(
        orderBy: ['id' => 'DESC'],
        filters: [
            'seller_id' => $vendorId,
            'seen_by_seller' => 0,
            'notification_receiver' => 'seller',
            'seen_notification' => 0
        ],
        whereNotNull: ['seller_id'],
        dataLimit: 'all'
    )->first();

    $chatting = $this->chattingRepo->getListWhereNotNull(
        filters: [
            'seller_id' => $vendorId,
            'seen_by_seller' => 0,
            'notification_receiver' => 'seller',
            'seen_notification' => 0
        ],
        whereNotNull: ['seller_id'],
    )->count();

    $this->chattingRepo->updateListWhereNotNull(
        filters: [
            'seller_id' => $vendorId,
            'seen_by_seller' => 0,
            'notification_receiver' => 'seller',
            'seen_notification' => 0
        ],
        whereNotNull: ['seller_id'],
        data: ['seen_notification' => 1]
    );

    return response()->json([
        'newMessagesExist' => $chatting,
        'message' => $chatting > 1 ? $chatting . ' ' . translate('New_Message') : translate('New_Message'),
        'url' => $this->buildVendorMessageNotificationUrl($latestChat),
    ]);
}
    private function getAdminChatUser(): Seller
{
    $adminUser = new Seller();
    $adminUser->id = 0;
    $adminUser->f_name = translate('admin');
    $adminUser->l_name = '';
    $adminUser->country_code = '';
    $adminUser->phone = '';

    return $adminUser;
}

private function buildVendorMessageNotificationUrl(object|null $latestChat): string
{
    if ($latestChat) {
        if ((int)$latestChat->admin_id === 0) {
            return route('vendor.messages.index', ['type' => 'admin']);
        }

        if (!empty($latestChat->delivery_man_id)) {
            return route('vendor.messages.index', [
                'type' => 'delivery-man',
                'delivery_man_id' => (int)$latestChat->delivery_man_id,
            ]);
        }

        if (!empty($latestChat->user_id)) {
            return route('vendor.messages.index', [
                'type' => 'customer',
                'customer_id' => (int)$latestChat->user_id,
            ]);
        }
    }

    return route('vendor.messages.index', ['type' => 'admin']);
}
}
