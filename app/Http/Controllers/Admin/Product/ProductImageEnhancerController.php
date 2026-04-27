<?php

namespace App\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use App\Services\OpenAIProductImageEnhancerService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductImageEnhancerController extends Controller
{
    public function __construct(private readonly OpenAIProductImageEnhancerService $imageEnhancerService)
    {
    }

    public function index(): View
    {
        return view('admin-views.product.ai-image-enhancer.index');
    }

    public function enhance(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
            'background' => ['nullable', 'in:white,transparent,studio'],
            'size' => ['nullable', 'in:1024x1024,1536x1024,1024x1536,auto'],
            'quality' => ['nullable', 'in:low,medium,high,auto'],
            'output_format' => ['nullable', 'in:png,webp,jpeg'],
            'extra_prompt' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $this->imageEnhancerService->enhance($request->file('image'), $validated);
        } catch (\Throwable $exception) {
            report($exception);
            ToastMagic::error($exception->getMessage());
            return back()->withInput();
        }

        ToastMagic::success(translate('image_enhanced_successfully'));

        return view('admin-views.product.ai-image-enhancer.index', [
            'result' => $result,
        ]);
    }
}
