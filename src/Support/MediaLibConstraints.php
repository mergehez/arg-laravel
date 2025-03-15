<?php /** @noinspection PhpMultipleClassesDeclarationsInOneFile */

namespace Arg\Laravel\Support;

class MediaLibConstraints
{
    public function __construct(
        public ?MediaLibConstraintsValues $required,
        public ?MediaLibConstraintsValues $max,
    ) {
    }

    public static function fromJson(?object $json): MediaLibConstraints
    {
        return new MediaLibConstraints(
            required: MediaLibConstraintsValues::fromJson($json?->required ?? null),
            max: MediaLibConstraintsValues::fromJson($json?->max ?? null),
        );
    }

    public function hasAny(): bool
    {
        return ($this->required && ($this->required->width || $this->required->height))
            || ($this->max && ($this->max->width || $this->max->height));
    }

    public function calcWidthHeight($imageSize): array
    {
        [$w, $h] = $imageSize;

        if ($this->required?->width) {
            $w = $this->required->width;
            $h = $this->required->height;
        } else {
            $maxWidth = $this->max->width ?? null;
            $maxHeight = $this->max->height ?? null;
            if (!$maxWidth) {
                $scale = $h > $maxHeight ? $maxHeight / $h : 1;
            } else {
                if (!$maxHeight) {
                    $scale = $w > $maxWidth ? $maxWidth / $w : 1;
                } else {
                    $scale = min($maxWidth / $w, $maxHeight / $h, 1); // last arg '1': don't let it scale up
                }
            }

            $w *= $scale;
            $h *= $scale;
        }
        return [$w, $h];
    }
}

class MediaLibConstraintsValues
{
    public function __construct(
        public ?int $width,
        public ?int $height,
    ) {
    }

    public static function fromJson(?object $json): MediaLibConstraintsValues
    {
        if (!$json) {
            return new MediaLibConstraintsValues(
                width: null,
                height: null,
            );
        }
        return new MediaLibConstraintsValues(
            width: $json->width ?? null,
            height: $json->height ?? null,
        );
    }
}