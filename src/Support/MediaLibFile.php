<?php

namespace Arg\Laravel\Support;

use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageInterface;

class MediaLibFile
{
    private EncoderInterface $encoder;

    public function __construct(
        public string $ext,
        public string $extClient,
        public array $dimensions,
        public ImageInterface $file,
    ) {
        if ($ext === 'jpg' && $extClient === 'jpeg') {
            $extClient = 'jpg';
        } else {
            if ($ext === 'jpeg' && $extClient === 'jpg') {
                $extClient = 'jpeg';
            }
        }

        if ($ext !== $extClient) {
            abort(401, 'File extension mismatch! Actual extension is \'.'.$ext.'\' but \'.'.$extClient.'\' was given!');
        }
        $this->encoder = $ext == 'jpeg' || $ext == 'jpg' ? new JpegEncoder() : ($ext == 'png' ? new PngEncoder() : new AutoEncoder());
    }

    public function getContent(): string
    {
        return $this->file->encode($this->encoder);
    }
}