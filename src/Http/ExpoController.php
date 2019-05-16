<?php

namespace NotificationChannels\ExpoPushNotifications\Http;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use NotificationChannels\ExpoPushNotifications\ExpoChannel;
use App\Seller;
use NotificationChannels\ExpoPushNotifications\Models\Interest;

class ExpoController extends Controller
{
    /**
     * @var ExpoChannel
     */
    private $expoChannel;

    /**
     * ExpoController constructor.
     *
     * @param ExpoChannel $expoChannel
     */
    public function __construct(ExpoChannel $expoChannel)
    {
        $this->expoChannel = $expoChannel;
    }

    /**
     * Handles subscription endpoint for an expo token.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expo_token'    =>  'required|string',
            'seller_token'    => 'required|string'
        ]);

        if ($validator->fails()) {
            return JsonResponse::create([
                'status' => 'failed',
                'error' => [
                    'message' => 'Expo Token is required',
                ],
            ], 422);
        }

        $token = $request->get('expo_token');
        $seller = Seller::where('token', $request->get('seller_token'))->first();
        $interest = $this->expoChannel->interestName($seller);

        try {
            Interest::where('key', $interest)->delete(); //purge old intrests for user before inserting a new one
            $this->expoChannel->expo->subscribe($interest, $token);
        } catch (\Exception $e) {
            return JsonResponse::create([
                'status'    => 'failed',
                'error'     =>  [
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }

        return JsonResponse::create([
            'status'    =>  'succeeded',
            'expo_token' => $token,
        ], 200);
    }

    /**
     * Handles removing subscription endpoint for the authenticated interest.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function unsubscribe(Request $request)
    {
        $seller = Seller::where('token', $request->get('seller_token'))->first();
        $interest = $this->expoChannel->interestName($seller);

        $validator = Validator::make($request->all(), [
            'expo_token'    =>  'sometimes|string',
        ]);

        if ($validator->fails()) {
            return JsonResponse::create([
                'status' => 'failed',
                'error' => [
                    'message' => 'Expo Token is invalid',
                ],
            ], 422);
        }

        $token = $request->get('expo_token') ?: null;

        try {
            $deleted = $this->expoChannel->expo->unsubscribe($interest, $token);
        } catch (\Exception $e) {
            return JsonResponse::create([
                'status'    => 'failed',
                'error'     =>  [
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }

        return JsonResponse::create(['deleted' => $deleted]);
    }
}
