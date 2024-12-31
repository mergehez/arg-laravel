<?php

namespace Arg\Laravel\Support;

class MediaLibConfig
{
    /**
     * @var MediaLibConfigSize[]
     */
    private array $allSizeConfigs;

    /**
     * @param  string  $baseDir
     * @param  MediaLibConfigSize  $baseSize
     * @param  MediaLibConfigSize[]  $resizes
     * @param  array<string>  $extensions
     */
    public function __construct(
        public string $baseDir,
        public MediaLibConfigSize $baseSize,
        public array $resizes,
        public array $extensions,
    ) {
        $this->extensions ??= ['jpeg', 'jpg', 'png', 'webp', 'gif'];
        $this->allSizeConfigs = array_merge([$this->baseSize], $this->resizes);
    }

    /**
     * @return MediaLibConfigSize[]
     */
    public function allSizeConfigs(): array
    {
        return $this->allSizeConfigs;
    }

    public static function createFromRequest(string $key = 'config'): MediaLibConfig
    {
        $requestConfig = request('config');
        if (is_array($requestConfig)) {
            $requestConfig = json_encode($requestConfig);
        }

        $config = json_decode($requestConfig);
        if (!$config) {
            return throw new \Exception('Invalid config');
        }

        return new MediaLibConfig(
            baseDir: $config->baseDir,
            baseSize: MediaLibConfigSize::fromJson($config->baseSize),
            resizes: array_map(fn($resize) => MediaLibConfigSize::fromJson($resize), $config->resizes),
            extensions: $config->extensions,
        );
    }
}