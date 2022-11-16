<?php

namespace App\Twill\Capsules\GoogleRecaptchas\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\Factory;
use App\Twill\Capsules\GoogleRecaptchas\Support\Transformer;
use App\Twill\Capsules\GoogleRecaptchas\Support\Validator as GoogleRecaptchaValidator;

class GoogleRecaptchaFrontController extends Controller
{
    public function show(): View|Factory
    {
        google_recaptcha();

        /** @var view-string $view */
        $view = 'google-recaptcha::front.form';

        return view($view, _transform(new Transformer()));
    }

    public function store(Request $request): array
    {
        $request->validate([
            'g-recaptcha-response' => ['required', 'string', new GoogleRecaptchaValidator()],
        ]);

        $response = google_recaptcha()->verify($request->get('g-recaptcha-response'));

        if (empty($response)) {
            return [
                'success' => false,
                'message' => 'Google recaptcha service error',
            ];
        }

        return $response->json();
    }
}
