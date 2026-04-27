@extends('layouts.admin.app')

@section('title', translate('AI_Product_Image_Enhancer'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <img src="{{ dynamicAsset(path: 'public/assets/back-end/img/inhouse-product-list.png') }}"
                     alt="{{ translate('AI_Product_Image_Enhancer') }}">
                {{ translate('AI_Product_Image_Enhancer') }}
            </h2>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3">{{ translate('Enhance_Product_Image') }}</h3>
                        <p class="text-muted">
                            {{ translate('Upload_a_product_photo_and_the_AI_agent_will_improve_lighting_background_sharpness_and_ecommerce_presentation') }}
                        </p>

                        <form action="{{ route('admin.products.ai-image-enhancer.enhance') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="form-group mb-3">
                                <label class="title-color" for="image">{{ translate('Product_Image') }}</label>
                                <input type="file" name="image" id="image" class="form-control" accept="image/png,image/jpeg,image/webp" required>
                                @error('image')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="title-color" for="background">{{ translate('Background') }}</label>
                                <select name="background" id="background" class="form-control">
                                    <option value="white" {{ old('background') === 'white' ? 'selected' : '' }}>{{ translate('White_Ecommerce_Background') }}</option>
                                    <option value="transparent" {{ old('background') === 'transparent' ? 'selected' : '' }}>{{ translate('Transparent_Background') }}</option>
                                    <option value="studio" {{ old('background') === 'studio' ? 'selected' : '' }}>{{ translate('Studio_Background') }}</option>
                                </select>
                                @error('background')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="title-color" for="size">{{ translate('Size') }}</label>
                                        <select name="size" id="size" class="form-control">
                                            <option value="1024x1024" {{ old('size') === '1024x1024' ? 'selected' : '' }}>1024x1024</option>
                                            <option value="1536x1024" {{ old('size') === '1536x1024' ? 'selected' : '' }}>1536x1024</option>
                                            <option value="1024x1536" {{ old('size') === '1024x1536' ? 'selected' : '' }}>1024x1536</option>
                                            <option value="auto" {{ old('size') === 'auto' ? 'selected' : '' }}>{{ translate('Auto') }}</option>
                                        </select>
                                        @error('size')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="title-color" for="quality">{{ translate('Quality') }}</label>
                                        <select name="quality" id="quality" class="form-control">
                                            <option value="high" {{ old('quality') === 'high' ? 'selected' : '' }}>{{ translate('High') }}</option>
                                            <option value="medium" {{ old('quality') === 'medium' ? 'selected' : '' }}>{{ translate('Medium') }}</option>
                                            <option value="low" {{ old('quality') === 'low' ? 'selected' : '' }}>{{ translate('Low') }}</option>
                                            <option value="auto" {{ old('quality') === 'auto' ? 'selected' : '' }}>{{ translate('Auto') }}</option>
                                        </select>
                                        @error('quality')
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="title-color" for="output_format">{{ translate('Output_Format') }}</label>
                                <select name="output_format" id="output_format" class="form-control">
                                    <option value="png" {{ old('output_format') === 'png' ? 'selected' : '' }}>PNG</option>
                                    <option value="webp" {{ old('output_format') === 'webp' ? 'selected' : '' }}>WEBP</option>
                                    <option value="jpeg" {{ old('output_format') === 'jpeg' ? 'selected' : '' }}>JPEG</option>
                                </select>
                                @error('output_format')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label class="title-color" for="extra_prompt">{{ translate('Extra_Instructions') }}</label>
                                <textarea name="extra_prompt" id="extra_prompt" class="form-control" rows="4" placeholder="{{ translate('Example_make_the_product_look_premium_but_keep_real_colors') }}">{{ old('extra_prompt') }}</textarea>
                                @error('extra_prompt')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                {{ translate('Enhance_Image') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="mb-3">{{ translate('Enhanced_Result') }}</h3>

                        @if(!empty($result))
                            <div class="text-center">
                                <img src="{{ $result['url'] }}" alt="{{ translate('Enhanced_Product_Image') }}" class="img-fluid rounded border mb-3" style="max-height: 560px; object-fit: contain;">
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <a href="{{ $result['url'] }}" target="_blank" class="btn btn-outline-primary">
                                        {{ translate('Open_Image') }}
                                    </a>
                                    <a href="{{ $result['url'] }}" download class="btn btn-secondary">
                                        {{ translate('Download') }}
                                    </a>
                                </div>
                                <div class="alert alert-light mt-3 text-start">
                                    <strong>{{ translate('Saved_Path') }}:</strong> {{ $result['path'] }}
                                </div>
                            </div>
                        @else
                            <div class="d-flex align-items-center justify-content-center border rounded" style="min-height: 360px;">
                                <div class="text-center text-muted p-4">
                                    <h4>{{ translate('No_Image_Enhanced_Yet') }}</h4>
                                    <p class="mb-0">{{ translate('Upload_a_product_image_to_generate_an_enhanced_version') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
