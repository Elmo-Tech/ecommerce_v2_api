<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ApiHttpFixtures
{
    public const PREFIX = '/api/v1/__f0-api';

    public const PRIVATE_VALUE = 'fixture-private-sql-value';

    public static function register(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('HTTP contract fixtures require the testing environment.');
        }

        Route::prefix(self::PREFIX)->group(static function (): void {
            Route::get('ok', static fn () => ApiResponse::success(
                data: ['example' => true],
                message: 'Fixture success',
            ));
            Route::post('created', static fn () => ApiResponse::success(
                data: ['id' => '9223372036854775807'],
                message: 'Fixture created',
                status: HttpStatusCode::Created,
            ));
            Route::get('no-content', static fn () => ApiResponse::noContent());
            Route::get('locale', static fn () => response()->json([
                'locale' => app()->getLocale(),
                'translatorLocale' => app('translator')->getLocale(),
            ]));
            Route::post('validate', static fn (ApiFixtureRequest $request) => ApiResponse::success(
                data: $request->validated(),
                message: 'Fixture validated',
            ));
            Route::get('authentication', static function (): never {
                throw new AuthenticationException(self::PRIVATE_VALUE);
            });
            Route::get('authorization', static function (): never {
                throw new AuthorizationException(self::PRIVATE_VALUE);
            });
            Route::get('fault', static function (): never {
                throw new RuntimeException('SQL SELECT password FROM private_fixture: '.self::PRIVATE_VALUE.' /private/source.php');
            });
            Route::get('custom-error', static function (): never {
                throw new class () extends RuntimeException {
                    public function render(): \Illuminate\Http\JsonResponse
                    {
                        return response()->json(['private' => ApiHttpFixtures::PRIVATE_VALUE], 502);
                    }
                };
            });
            Route::get('misleading-success', static function (): never {
                throw new class () extends RuntimeException {
                    public function render(): \Illuminate\Http\JsonResponse
                    {
                        return response()->json(['private' => ApiHttpFixtures::PRIVATE_VALUE], 200);
                    }
                };
            });
            Route::get('misleading-redirect', static function (): never {
                throw new class () extends RuntimeException {
                    public function render(): \Illuminate\Http\RedirectResponse
                    {
                        return redirect('/fixture-private-target');
                    }
                };
            });
            Route::post('validate-wildcard', static function (\Illuminate\Http\Request $request) {
                $request->validate([
                    'customer.name' => ['required', 'string'],
                    'items.*.quantity' => ['required', 'integer', 'min:1'],
                ]);

                return ApiResponse::success(message: 'Fixture validated');
            });
            Route::post('validate-root-wildcard', static function (\Illuminate\Http\Request $request) {
                $request->validate(['*.quantity' => ['required', 'integer', 'min:1']]);

                return ApiResponse::success(message: 'Fixture validated');
            });
            Route::post('validate-manual', static function (\Illuminate\Http\Request $request): never {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    (string) $request->input('field') => ['fixture-private-manual'],
                ]);
            });
            Route::get('report-twice', static function () {
                // Two distinct, short-lived exceptions in the same request.
                report(new RuntimeException('fixture-private-manual-first'));
                report(new RuntimeException('fixture-private-manual-second'));

                return ApiResponse::success(message: 'Fixture done');
            });
            Route::get('redirect', static function (): never {
                throw new HttpResponseException(redirect('/fixture-native-target'));
            });
            Route::get('download', static fn () => response()->streamDownload(
                static fn () => print('fixture'),
                'fixture.txt',
            ));

            foreach ([400, 401, 404, 409, 418, 422, 429, 500, 503] as $status) {
                Route::get('status/'.$status, static function () use ($status): never {
                    $headers = match ($status) {
                        401 => ['WWW-Authenticate' => 'Bearer realm="foundation-fixture"'],
                        429 => ['Retry-After' => '7'],
                        503 => ['Retry-After' => '17'],
                        default => [],
                    };
                    throw new HttpException($status, self::PRIVATE_VALUE, null, $headers);
                });
            }
        });

        Route::get('/api/v10/__f0-api/legacy-fault', static function (): never {
            throw new RuntimeException('legacy-fixture-native-error');
        });
        Route::get('/api/v10/__f0-api/legacy', static function (): never {
            throw new HttpException(404, 'Legacy response');
        });
    }
}
