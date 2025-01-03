<?php

namespace Arg\Laravel\Controllers;

use Arg\Laravel\Support\MediaLibConfig;
use Arg\Laravel\Support\MediaLibConfigSize;
use Arg\Laravel\Support\MediaLibFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Decoders\DataUriImageDecoder;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Laravel\Facades\Image as InterventionImage;
use JsonException;
use Throwable;

class ArgMediaLibraryController extends ArgBaseController
{
    private Filesystem $storage;
    private MediaLibConfig $config;

    public function __construct()
    {
        $this->storage = Storage::disk('public-folder');
        // $requestConfig = request('config');
        // $requestConfig = is_string($requestConfig)
        //     ? json_decode($requestConfig, true) :
        //     ($requestConfig ?? [
        //         'baseDir' => 'uploads',
        //         // 'baseSize' => [ 'name' => 'xl', 'nameSuffix' => '-xl', 'scale' => 100 ],
        //         'baseSize' => ['name' => 'Original', 'nameSuffix' => '', 'scale' => 100],
        //         'resizes' => [
        //             ['name' => 'Large', 'nameSuffix' => '-lg', 'scale' => 125],
        //             ['name' => 'Medium', 'nameSuffix' => '-md', 'scale' => 75],
        //             ['name' => 'Small', 'nameSuffix' => '-sm', 'scale' => 50],
        //         ],
        //         'extensions' => ['jpeg', 'jpg', 'png', 'webp', 'gif'],
        //     ]);
        // $requestConfig['extensions'] ??= ['jpeg', 'jpg', 'png', 'webp', 'gif'];

        $this->config = MediaLibConfig::createFromRequest('config');
        // $this->config['allSizeConfigs'] =
        //     array_merge([$this->config->baseSize], $this->config['resizes']);
    }

    public static function registerRoutes($middleware = ['auth:web']): void
    {
        Route::prefix('arg-media-library')->name('arg-media-library.')->middleware($middleware)->group(function () {
            Route::post('setup', [ArgMediaLibraryController::class, 'setup'])->name('setup');
            Route::post('select-files', [ArgMediaLibraryController::class, 'selectFiles'])->name('select-files');
            Route::post('delete-files', [ArgMediaLibraryController::class, 'deleteFiles'])->name('delete-files');
            Route::post('delete-folder', [ArgMediaLibraryController::class, 'deleteFolder'])->name('delete-folder');
            Route::post('create-folder', [ArgMediaLibraryController::class, 'createFolder'])->name('create-folder');
            Route::post('upload-files', [ArgMediaLibraryController::class, 'uploadFiles'])->name('upload-files');
            Route::post('rename-file', [ArgMediaLibraryController::class, 'renameFile'])->name('rename-file');
            Route::post('ckeditor-upload', [ArgMediaLibraryController::class, 'ckeditorUpload'])->name('ckeditor-upload');
        });
    }

    public function ckeditorUpload(Request $request)
    {
        // $data = $request->validate([
        //     'files' => ['required'],
        // ]);
        // dd($request->file('upload'));

        // $request->merge([
        //     'files' => [$request->file('upload')],
        //     'path' => $this->config->baseDir,
        //     'settings' => json_encode([['format' => 'jpeg', 'name' => 'New Image']])
        // ]);

        $file = $request->file('upload');
        $name = $file->getClientOriginalName();
        $name = pathinfo($name, PATHINFO_FILENAME);
        $ext = $file->extension();

        $this->config = [
            'baseDir' => 'uploads/ckeditor',
            'baseSize' => ['name' => 'Original', 'nameSuffix' => '', 'scale' => 100],
            'resizes' => [

            ],
            'extensions' => [$ext],
        ];

        $this->_uploadFiles([$file], $this->config->baseDir, json_encode([['format' => $ext, 'name' => $name]]));

        return response()->json([
            'url' => url($this->config->baseDir.'/'.$name.'.'.$ext)
        ]);
    }

    public function createFolder(Request $request): bool
    {

        $request->validate([
            'name' => ['required', 'string'],
            'path' => ['nullable', 'string']
        ]);

        $name = preg_replace('/\s+/', '-', $request->input('name'));
        $path = $this->ensureBaseDir($request->input('path'));
        $this->storage->makeDirectory($path.'/'.$name);

        return true;
    }

    public function deleteFolder(Request $request): bool
    {

        $request->validate([
            'folder' => ['required', 'string']
        ]);

        $folderPath = $this->ensureBaseDir($request->input('folder'));
        // $folderPath = trim(str_replace(self::baseDir, '', $request->input('folder')), '/');

        $this->storage->deleteDirectory($folderPath);
        // $this->storage->deleteDirectory(self::dirLg . $folderPath);
        // $this->storage->deleteDirectory(self::dirMd . $folderPath);
        // $this->storage->deleteDirectory(self::dirSm . $folderPath);

        return true;
    }

    public function deleteFiles(Request $request): bool
    {

        $request->validate([
            'files' => ['required', 'array'],
            'path' => ['required', 'string'],
        ]);

        $fileNames = $request->input('files');

        $path = $this->ensureBaseDir($request->input('path'));

        foreach ($fileNames as $fileNameNoSuffix) {
            $pathInfo = pathinfo($fileNameNoSuffix);
            // $nameWithoutSuffix = str_replace($this->config->baseSize['nameSuffix'] . '.' . $ext, '' , $pathInfo['basename']);
            $allSizeConfigs = $this->config->allSizeConfigs();
            foreach ($allSizeConfigs as $cfg) {
                $imgPath = $path.'/'.$this->nameToTemplate($cfg, $pathInfo['filename'], $pathInfo['extension']);
                $this->storage->delete($imgPath);
            }
            // $this->doForEachSize($file, fn($path) => $this->storage->delete($path));
        }

        return true;
    }

    private function _fromBase64(string $base64File)
    {
        // Get file data base64 string
        $fileData = base64_decode(Arr::last(explode(',', $base64File)));
        return $fileData;

        // // Create temp file and get its absolute path
        // $tempFile = tmpfile();
        // $tempFilePath = stream_get_meta_data($tempFile)['uri'];
        //
        // // Save file data in file
        // file_put_contents($tempFilePath, $fileData);
        //
        // $tempFileObject = new File($tempFilePath);
        // $file = new UploadedFile(
        //     $tempFileObject->getPathname(),
        //     $tempFileObject->getFilename(),
        //     $tempFileObject->getMimeType(),
        //     0,
        //     true // Mark it as test, since the file isn't from real HTTP POST.
        // );
        //
        // // Close this file after response is sent.
        // // Closing the file will cause to remove it from temp director!
        // app()->terminating(function () use ($tempFile) {
        //     fclose($tempFile);
        // });
        //
        // // return UploadedFile object
        // return $file;
    }

    public function uploadFiles(Request $request): bool
    {
        $settings = $request->input('settings');
        $files = $request->file('files');
        if (!$files) {
            $files = array_map(function ($base64Url) {
                $ext = explode(';base64,', $base64Url)[0];
                $ext = explode('/', $ext)[1];
                // $file = InterventionImage::read(base64_decode(explode(';base64,', $base64Url)[1]), new DataUriImageDecoder());
                $file = InterventionImage::read($base64Url, new DataUriImageDecoder());
                return new MediaLibFile(
                    ext: $ext,
                    extClient: $ext,
                    dimensions: [$file->width(), $file->height()],
                    file: $file
                );
                // return $this->_fromBase64($file);
            }, $request->input('files'));
        } else {
            $files = array_map(function ($file) {
                return new MediaLibFile(
                    ext: $file->extension(),
                    extClient: $file->getClientOriginalExtension(),
                    dimensions: $file->dimensions() ?? getimagesize($file),
                    file: InterventionImage::read($file)
                );
            }, $files);
        }
        // dd($files);
        return $this->_uploadFiles(
            $files,
            $request->input('path'),
            $request->input('settings')
        );
    }

    private function _uploadFiles($files, $path, $settingsStr): bool
    {
        // // dd($request->all());
        // $request->validate([
        //     'files' => ['required'],
        //     'files.*' => ['file', 'mimes:'.implode(',', $this->config['extensions'])],
        //     'path' => ['nullable', 'string'],
        //     'settings' => ['required']
        // ]);

        // $files = $request->file('files');
        try {
            $settings = json_decode($settingsStr, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(401, 'settings was not valid json!');
        }
        $uploadPath = $this->ensureBaseDir($path);

        /** @var MediaLibFile $file */
        foreach ($files as $index => $file) {
            $format = $settings[$index]['format'];
            $name = preg_replace('/\s+/', '-', $settings[$index]['name']);

            if (!in_array($file->ext, $this->config->extensions)) {
                continue;
            }

            $cfgBase = $this->config->baseSize;

            $append = '';
            $counter = 1;
            while ($this->storage->exists("$uploadPath/".$this->nameToTemplate($cfgBase, "$name$append", $format))) {
                $append = "-$counter";
                $counter++;
            }
            // $imageSize = $file->dimensions() ?? getimagesize($file);
            $imageSize = $file->dimensions;
            if (!$imageSize) { // if not image file (e.g. pdf)
                try {
                    if (!$this->storage->put("$uploadPath/".$this->nameToTemplate($cfgBase, "$name$append", $format), $file->getContent())) {
                        abort(500, "Couldn't upload..");
                    }
                } catch (Throwable $e) {
                    abort(400, 'File not found! '.$e->getMessage());
                }
                continue;
            }

            // from here on, it's image file

            $originalImage = $file->file;//InterventionImage::read($file);
            [$w, $h] = $imageSize;
            if ($cfgBase->uploadConstraints->hasAny()) {
                [$w, $h] = $cfgBase->uploadConstraints->calcWidthHeight($imageSize);

                if ($w !== $imageSize[0] && $h !== $imageSize[1]) {
                    $originalImage = $originalImage->resize($w, $h);
                } else {
                    if ($w !== $imageSize[0] || $h !== $imageSize[1]) {
                        // if same, let the other decide aspect ratio
                        $w = $w === $imageSize[0] ? null : $w;
                        $h = $h === $imageSize[1] ? null : $h;
                        $originalImage = $originalImage->resize($w, $h);
                    }
                }
            }
            $encoder = $format == 'jpeg' || $format == 'jpg' ? new JpegEncoder() : ($format == 'png' ? new PngEncoder() : new AutoEncoder());
            $originalImage = $originalImage->encode($encoder);// , $cfgBase['scale']);

            $this->storage->put("$uploadPath/".$this->nameToTemplate($cfgBase, "$name$append", $format), $originalImage);

            foreach ($this->config->resizes as $cfg) {
                $newW = $w === null ? null : round($w * $cfg->scale / 100);
                $newH = $h === null ? null : round($h * $cfg->scale / 100);

                $imageOutput = $file->file //InterventionImage::read($file)
                ->resize($newW, $newH)     //, $w === null || $h === null ? static fn ($c)  => $c->aspectRatio() : null)
                ->encode($encoder);
                $this->storage->put("$uploadPath/".$this->nameToTemplate($cfg, "$name$append", $format), $imageOutput);
            }
        }
        return true;
    }

    private function getFiles(string $directory): array
    {
        $files = $this->storage->files($directory);
        return array_filter($files, fn($file) => in_array(pathinfo($file, PATHINFO_EXTENSION), $this->config->extensions));
    }

    public function setup(Request $request): array
    {
        // return $this->storage->allDirectories('/');
        $request->validate([
            'path' => ['nullable', 'string']
        ]);

        $fileOnlyMode = $request->get('filesOnly', false);

        $directories = $fileOnlyMode ? [] : $this->storage->allDirectories($this->config->baseDir);
        if (!$fileOnlyMode) {
            $directories = array_merge([$this->config->baseDir], $directories);
        }
        // return $directories;
        $directories_array = [];

        foreach ($directories as $index => $directory) {
            // $directory2 = trim(Str::replace(self::baseDir , '' , $directory), '/') ?: '/';

            $info = [
                'id' => $directory,
                'directory' => $directory,
                'url_base' => url($directory),
                'path' => pathinfo($directory),
                'parent_id' => null,
                'inner' => [],
                'indent' => 0,
                'fileCount' => 0
            ];

            $key = array_search($info['path']['dirname'], array_column($directories_array, 'directory'), true);

            if (is_numeric($key)) {
                $directories_array[$key]['inner'][] = $info['id'];
                $info['parent_id'] = $directories_array[$key]['id'];
                $info['indent'] = $directories_array[$key]['indent'] + 1;
                $info['fileCount'] = count($this->getFiles($directory));
            }

            $directories_array[] = $info;
        }

        $directory = $this->ensureBaseDir($request->input('path'));
        $files = collect($this->getFiles($directory))->map(fn($f) => [$f, pathinfo($f)]);
        // dd($directory, $files);
        $files_array = collect();
        $filesToSkip = [];

        /* @var $iValue array */
        foreach ($files as $iValue) {
            // foreach ($files as $single_file){
            [$single_file, $pathInfo] = $iValue;
            // $pathInfo = pathinfo($single_file);

            // if ($filesToSkip->contains($pathInfo['basename'])) {
            if (in_array($pathInfo['basename'], $filesToSkip)) {
                continue;
            }

            $ext = $pathInfo['extension'];

            if (!in_array($ext, $this->config->extensions)) {
                continue;
            }

            //check if the name of file matches the template of base size config
            if (!str_contains($pathInfo['basename'], $this->config->baseSize->nameSuffix.'.'.$ext)) {
                continue;
            }
            $nameWithoutSuffix = str_replace($this->config->baseSize->nameSuffix.'.'.$ext, '', $pathInfo['basename']);

            $file_info = [];
            $file_info['name'] = $nameWithoutSuffix.'.'.$ext;

            if ($files_array->contains('name', '=', $file_info['name'])) {
                continue;
            }

            $file_info['url'] = [
                $this->config->baseSize->name => self::removeDomain(url($single_file)),
            ];
            foreach ($this->config->resizes as $cfg) {
                $imgPath = $this->nameToTemplate($cfg, $nameWithoutSuffix, $ext);
                $existing = $files->filter(fn($f) => $f[1]['basename'] === $imgPath);
                if (count($existing) === 0) {
                    continue;
                }
                $filesToSkip[] = $imgPath;
                $file_info['url'][$cfg->name] = self::removeDomain(url($imgPath));
            }

            $file_info['time'] = $this->storage->lastModified($single_file);
            $file_info['size'] = $this->storage->size($single_file);
            $file_info['path'] = $single_file;

            $files_array->push($file_info);
        }

        if ($fileOnlyMode) {
            return ['files' => $files_array];
        }

        $baseDir = $directories_array[0];
        $baseDir['path']['basename'] = $baseDir['directory'];
        array_splice($directories_array, 0, 1);
        // $baseDir['path']['basename'] = $baseDir['name'];
        return ['base_dir' => $baseDir, 'directories' => $directories_array, 'files' => $files_array];
    }

    public function selectFiles(Request $request): array
    {
        $request->validate([
            'path' => ['required', 'string'],
            'files' => ['required', 'array'] // files names without 'nameSuffix'
        ]);

        $files = [];

        $debug = collect();
        foreach ($request->get('files') as $fileNameNoSuffix) {
            $pathInfo = pathinfo($fileNameNoSuffix);
            $pathNoExt = $request->get('path').'/'.$pathInfo['filename'];
            $ext = $pathInfo['extension'];

            $result = [
                'fileNameNoSuffix' => $fileNameNoSuffix,
                'selected' => $this->config->baseSize->name,

                'select' => [
                    'url' => self::removeDomain(url($this->nameToTemplate($this->config->baseSize, $pathNoExt, $ext))),
                    'alt' => ''
                ],
                'values' => [],
            ];

            $allSizeConfigs = $this->config->allSizeConfigs();
            foreach ($allSizeConfigs as $cfg) {
                $imgPath = $this->nameToTemplate($cfg, $pathNoExt, $ext);
                if (!file_exists($imgPath)) {
                    $debug[] = $imgPath;
                    continue;
                    // abort(404,  "$imgPath does not exist!");
                }
                $imgSize = getimagesize($this->storage->path($imgPath)) ?: [0, 0];

                $result['values'][$cfg->name] = [
                    'path' => $imgPath,
                    'url' => self::removeDomain(url($imgPath)),
                    'size' => $this->fileSizeFormat($imgPath),
                    'height' => $imgSize[1],
                    'width' => $imgSize[0],
                ];
            }

            if (count(array_keys($result['values'])) === 0) {
                return ['error' => $debug];
            }

            $baseVal = $result['values'][$this->config->baseSize->name];
            $result['aspectRatio'] = $baseVal['height'] ? $baseVal['width'] / $baseVal['height'] : 0;
            $files[] = $result;
        }

        return ['files' => $files];
    }

    public function renameFile(Request $request)
    {
        $data = $request->validate([
            'old' => ['required', 'string'],
            'new' => ['required', 'string'],
        ]);

        $oldPath = $this->ensureBaseDir($data['old']);
        $newPath = $this->ensureBaseDir($data['new']);

        if (!$this->storage->move($oldPath, $newPath)) {
            abort(500, 'Could not rename file!');
        }

        return $newPath;
    }

    private function ensureBaseDir($path): string
    {
        if (str_starts_with($path, $this->config->baseDir)) {
            return $path;
        }

        return $this->config->baseDir.'/'.Str::ltrim($path, '/');
        // return $this->config->baseDir . '/' . (trim(Str::replace($this->config->baseDir , '' , $path), '/') ?: '');
    }
    // private function ensureMaxUploadDimensions($path){
    //     return $this->config->baseDir . "/" . (trim(Str::replace($this->config->baseDir , '' , $path), '/') ?: '');
    // }

    private function nameToTemplate(MediaLibConfigSize $sizeConf, $name, $ext): string
    {
        return $name.$sizeConf->nameSuffix.'.'.$ext;
    }

    private function fileSizeFormat($path): string
    {
        $size = $this->storage->size($path);
        $units = array('B', 'KB', 'MB');

        $power = $size > 0 ? floor(log($size, 1024)) : 0;

        return number_format($size / (1024 ** $power), 2).' '.$units[$power];
    }

    private static function removeDomain($url, $removeFirstSlash = false)
    {
        $url = parse_url($url, PHP_URL_PATH);
        return $removeFirstSlash ? trim($url, '/') : $url;
    }
}
