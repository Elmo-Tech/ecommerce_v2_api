<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Http\ApiFieldValidator;
use App\Core\Http\ApiRequestContext;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Use Laravel's native factory and rule engine. The v1 adapter
        // exposes only field provenance for safe exception serialization.
        // Resolve the current request at validator creation; do not capture
        // a Request, tenant or locale inside a long-lived factory callback.
        $this->callAfterResolving('validator', static function (Factory $factory): void {
            $factory->resolver(static function (
                Translator $translator,
                array $data,
                array $rules,
                array $messages,
                array $attributes,
            ): Validator {
                $request = app()->bound('request') ? app('request') : null;
                if ($request instanceof Request && ApiRequestContext::active($request)) {
                    return new ApiFieldValidator($translator, $data, $rules, $messages, $attributes);
                }

                return new Validator($translator, $data, $rules, $messages, $attributes);
            });
        });
    }
}
