<?php

namespace Arg\Laravel\Support;

class MediaLibConfigSize
{
    public function __construct(
        public string $name,
        public string $nameSuffix,
        public float $scale,
        public MediaLibConstraints $uploadConstraints,
    ) {
    }

    public static function fromJson(object $json): MediaLibConfigSize
    {
        return new MediaLibConfigSize(
            name: $json->name,
            nameSuffix: $json->nameSuffix ?? '',
            scale: $json->scale,
            uploadConstraints: MediaLibConstraints::fromJson($json->uploadConstraints ?? null),
        );
    }
}