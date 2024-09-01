<?php

namespace Arg\Laravel\Support;

use Arg\Laravel\Middleware\ArgSetLocale;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Arg
{
    /**
     * Call this in bootstrap/app.php > withMiddleware() method
     */
    public static function registerMiddlewares(Middleware $middleware, $handleInertiaClass): void
    {
        $middleware->web(append: [
            $handleInertiaClass,
            ArgSetLocale::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $r) => route('page.login', ['redirect_url' => $r->fullUrl()]));
    }

    public static function withExceptionFn(Exceptions $exceptions): void
    {
        $simplifyFile = function (&$file) {
            $p = explode('/', $file);
            if (str_contains($file, '/tirsik3/')) {
                $file = explode('/tirsik3/', $file)[1];
            }
            $file = str_replace('vendor/laravel/framework/src/Illuminate/', 'Illuminate/', $file);

            return $p;
        };
        $func = function (string $content) use ($simplifyFile) {
            /** @var array $json */
            $json = json_decode($content, true);
            if (! $json || ! ($json['trace'] ?? false)) {
                return null;
            }

            $trace = $json['trace'];
            $firstProjFile = null;
            $i = 0;
            $mappedTrace = array_map(function ($t) use (&$firstProjFile, &$i, $simplifyFile) {
                $p = $simplifyFile($t['file']);
                $x = [
                    'file' => array_pop($p).':'.($t['line'] ?? ''),
                    'path' => $t['file'],
                    'function' => $t['function'],
                    'class' => $t['class'] ?? '',
                    'type' => $t['type'] ?? '',
                ];

                if (! $firstProjFile && str_starts_with($t['file'], 'app/')) {
                    $firstProjFile = [
                        'index' => $i,
                        't' => [
                            ...$x,
                            'index' => $i,
                        ],
                    ];
                }
                $i++;

                return $x;
            }, array_filter($trace, function ($t) {
                return $t['file'] ?? false;
            }));

            $simplifyFile($json['file']);
            $data = [
                'message' => $json['message'],
                'line' => $json['line'],
                'file' => $json['file'],
                'exception' => $json['exception'],
                'projFile' => null,
                'trace' => $mappedTrace,
            ];

            if ($firstProjFile && $firstProjFile['index'] > 1) {
                $data['projFile'] = $firstProjFile['t'];
            }

            return $data;
        };
        $exceptions->respond(function (Response $response) use ($func) {
            if ($response->getStatusCode() >= 500) {
                try {
                    Log::debug('Exception responsex', ['status' => $response->getStatusCode()]);
                    if ($response->headers->get('Content-Type') == 'application/json') {
                        $content = $response->getContent();
                        if ($content && ! str_contains($content, 'Maximum execution time')) {
                            $newContent = $func($content);
                            if ($newContent && json_validate($j = json_encode($newContent))) {
                                $response->setContent($j);
                            }
                        }
                    }
                } catch (Throwable) {
                }
            } else {
                try {
                    $exceptionLog = json_decode(File::get(storage_path('logs/exception.json')), true);
                    $log = json_decode($response->getContent());
                    $log->status = $response->getStatusCode();
                    $exceptionLog[] = $log;
                    File::put(storage_path('logs/exception.json'), json_encode($exceptionLog, JSON_PRETTY_PRINT));
                } catch (Throwable) {
                }
            }

            return $response;
        });
    }
}
