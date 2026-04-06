<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Devrabiul\ToastMagic\Facades\ToastMagic;

class RecaptchaService
{
    public static function verify(string $token, ?string $action = null): bool
    {
        $secretKey = getWebConfig(name: 'recaptcha')['secret_key'];

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => request()->ip(),
        ]);

        $data = $response->json();

        if (!($data['success'] ?? false)) {
            ToastMagic::error(translate('ReCAPTCHA_Failed'));
            return false;
        }

        if (($data['score'] ?? 0) < 0.5) {
            ToastMagic::error(translate('ReCAPTCHA_Score_Too_Low_Please_Try_Again'));
            return false;
        }

        if ($action !== null && ($data['action'] ?? '') !== $action) {
            ToastMagic::error(translate('ReCAPTCHA_Action_Invalid'));
            return false;
        }

        return true;
    }

    public static function verificationStatus(object|array $request, string $session, ?string $action = 'default', ?bool $firebase = false): array
    {
        $firebaseOTPVerification = getWebConfig(name: 'firebase_otp_verification') ?? [];

        $requestData = is_array($request) ? $request : $request->all();

        $defaultCaptchaValue = $requestData['default_captcha_value'] ?? null;
        $recaptchaResponse = $requestData['g-recaptcha-response']
            ?? $requestData['firebase-auth-recaptcha-response']
            ?? null;

        if ($firebase && $firebaseOTPVerification && !empty($firebaseOTPVerification['status'])) {
            if (empty($recaptchaResponse)) {
                return [
                    'status' => false,
                    'message' => translate('ReCAPTCHA_Failed'),
                ];
            }

            return [
                'status' => true,
                'message' => translate('ReCAPTCHA_verification_success.'),
            ];
        }

        $recaptcha = getWebConfig(name: 'recaptcha');

        if (isset($recaptcha) && !empty($recaptcha['status']) && !$defaultCaptchaValue) {
            try {
                validator(
                    array_merge($requestData, ['g-recaptcha-response' => $recaptchaResponse]),
                    [
                        'g-recaptcha-response' => [
                            function ($attribute, $value, $fail) use ($action) {
                                if (empty($value)) {
                                    $fail(translate('ReCAPTCHA_verification_failed.'));
                                    return;
                                }

                                if (!RecaptchaService::verify(token: $value, action: $action)) {
                                    $fail(translate('ReCAPTCHA_verification_failed.'));
                                }
                            },
                        ],
                    ]
                )->validate();
            } catch (\Illuminate\Validation\ValidationException $e) {
                return [
                    'status' => false,
                    'message' => $e->validator->errors()->first('g-recaptcha-response'),
                ];
            }
        } elseif (strtolower((string) session($session)) !== strtolower((string) $defaultCaptchaValue)) {
            return [
                'status' => false,
                'message' => translate('ReCAPTCHA_failed.'),
            ];
        }

        if ($defaultCaptchaValue !== null && strtolower((string) session($session)) === strtolower((string) $defaultCaptchaValue)) {
            session()->forget($session);
        }

        session()->forget($session);

        return [
            'status' => true,
            'message' => translate('ReCAPTCHA_verification_success.'),
        ];
    }
}
