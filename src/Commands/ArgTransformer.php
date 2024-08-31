<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Arg\Laravel\Commands;

use Arg\Laravel\Enums\ArgBaseEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use UnitEnum;

abstract class ArgTransformer extends Command
{
    protected $signature = 'arg:transform {--force}';
    protected $description = 'Command description';


    abstract protected function getDisplayLangKeys(): array;
    abstract protected function handle(): void;

    private int $time = 0;
    /**
     * @param  array<EnumDef>  $enums
     * @return void
     */
    public function _handle(array $enums): void
    {
        $this->time = time();
        $targetDir = 'public/lang';
        if (! file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $success = $this->convertTranslations();

        $contents = [];
        foreach ($enums as $enum) {
            $contents[] = $this->assocToEnum($enum->arr, $enum->enumName, $enum->typeName, $enum->pretty, $enum->asObject);
        }

        $success = $success && file_put_contents('resources/js/Helpers/generated_enums.ts', implode("\n", $contents));

        if ($success) {
            $this->info(" -> Localization: (Re)converted json to php files. Location: $targetDir/");
        } else {
            $this->error(' -> Localization: Conversion failed.');
        }
    }

    protected function assocToEnum($arr, $enumName, $typeName, $pretty = false, $asObject = false): string
    {
        $res = $this->enumToJsObject($arr, $pretty, $asObject);
        $res = $asObject ? "export const $enumName = $res as const;" : "export enum $enumName $res";
        if ($typeName) {
            $res .= "\nexport type T$typeName = keyof typeof $enumName;";
        }

        return $res;
    }

    private function enumToJsObject($arr, $pretty, $asObject, $depth = 0): string
    {
        $res = [];
        foreach ($arr as $k => $v) {
            $sep = $asObject ? ':' : ' =';

            if($asObject && str_contains($k, '-')) {
                $k = "'$k'";
            }

            if (is_array($v)) {
                if (! Arr::isAssoc($v)) {
                    $res[] = "$k$sep ".json_encode($v).',';
                } else {
                    $v = $this->enumToJsObject($v, $pretty, $asObject, $depth + 1);
                    $res[] = "$k$sep $v,";
                }
            } else {
                if ($v instanceof UnitEnum) {
                    $k = $v->name;
                    $v = $v->value;
                }
                if (! $asObject && is_int($k)) {
                    $k = "_$k";
                }

                if (is_int($v)) {
                    $res[] = "$k$sep $v,";
                } elseif (str_contains($v, "'")) {
                    $res[] = "$k$sep \"$v\",";
                } else {
                    $res[] = "$k$sep '$v',";
                }
            }
        }
        if ($pretty) {
            $tabs = str_repeat("\t", $depth);
            $res = $tabs.implode("\n\t".$tabs, $res);
        } else {
            $tabs = '';
            $res = implode(' ', $res);
        }

        return "{\n\t$res\n$tabs}";
    }

    private function convertTranslations(): bool
    {
        $targetDir = 'public/lang';
        $statePath = '.idea/JsonToPhpState.json';

        if (! file_exists($statePath)) {
            file_put_contents($statePath, '{}');

            return true;
        }
        $state = json_decode(file_get_contents($statePath), true);
        $arr = [
            new TrConfig('lang/*.json', '', 'translations.js'),
        ];
        $arrNeedConversion = [false, false, false];

        $firstLangKey = config('app.locale');

        for ($i = 0; $i < count($arr); $i++) {
            $globPathX = $arr[$i]->globPath;
            $typeX = $arr[$i]->type;
            $jsFileNameX = $arr[$i]->jsFileName;
            $jsons = glob($globPathX);
            if (! $jsons) {
                $this->error(" -> Localization: No json files found using pattern: '$globPathX'");
                exit(1);
            }

            $arrNeedConversion[$i] = $this->needsConversion($state, $jsFileNameX, $jsons);
            if (! $arrNeedConversion[$i]) {
                $this->info(" -> Localization: No changes detected in json files for pattern: '$globPathX'");

                continue;
            } else {
                $this->info(" -> Localization: converting json files for pattern: '$globPathX'");
            }

            $langVals = [];
            $displayLangKeys = $this->getDisplayLangKeys();
            foreach ($jsons as $jsonFile) {
                $langCode = str_replace('.json', '', basename($jsonFile));
                // dd(str_replace('.json', '', basename($jsonFile)));

                if (! in_array($langCode, $displayLangKeys)) {
                    continue;
                }

                $decoded = json_decode(file_get_contents($jsonFile), true);
                file_put_contents("$targetDir/$langCode$typeX.php", "<?php\n\nreturn ".var_export($decoded, true).';');

                if (! array_key_exists($langCode, $langVals)) {
                    $langVals[$langCode] = [];
                }

                foreach ($decoded as $k => $v) {
                    $k = trim($k);
                    $langVals[$langCode][$k] = $v;
                }
            }

            $lineBreak = ' ';
            $langValsJsStr = '';
            $langValsJsStr2 = '';
            foreach ($langVals as $lang => $vals) {
                $langValsJsStr .= "window.__tr_{$typeX}_$lang = ".json_encode($vals, strlen(trim($lineBreak)) > 0 ? JSON_PRETTY_PRINT : 0).";$lineBreak";
                $langValsJsStr2 .= "export const __tr_{$typeX}_$lang = ".json_encode($vals, strlen(trim($lineBreak)) > 0 ? JSON_PRETTY_PRINT : 0)." as const;$lineBreak";
            }
            file_put_contents('resources/js/Helpers/'.str_replace('.js', '.ts', $jsFileNameX), $langValsJsStr2);

            $langValsJsStr .= 'window.__getByLocale'.ucfirst($typeX).' = (locale) => {'.$lineBreak;
            foreach ($langVals as $lang => $vals) {
                $langValsJsStr .= "  if (locale === '$lang') return __tr_{$typeX}_$lang;$lineBreak";
            }
            $langValsJsStr .= "  return __tr_{$typeX}_$firstLangKey;$lineBreak}$lineBreak";

            if (! file_put_contents("public/$jsFileNameX", "$lineBreak$langValsJsStr")) {
                $this->error(' -> Localization: Failed to write js file: '.$jsFileNameX);

                return false;
            }
        }

        $trKeysContent2 = '';
        for ($i = 0; $i < count($arr); $i++) {
            $trKeysContent2 .= "import {__tr_{$arr[$i]->type}_$firstLangKey} from './".str_replace('.js', '', $arr[$i]->jsFileName)."';\n";
        }
        $trKeysContent2 .= "\n";
        for ($i = 0; $i < count($arr); $i++) {
            $trKeysContent2 .= 'export type TrKey'.ucfirst($arr[$i]->type)." = keyof typeof __tr_{$arr[$i]->type}_$firstLangKey;\n";
        }
        $success = file_put_contents('resources/js/Helpers/tr_keys.ts', $trKeysContent2);

        $appBladeStr = file_get_contents('resources/views/app.blade.php');
        for ($i = 0; $i < count($arr); $i++) {
            if (! $arrNeedConversion[$i]) {
                continue;
            }
            $fileName = $arr[$i]->jsFileName;
            $pattern = '~(<!-- last update )[0-9 .:-]+( -->[\n\s]+<script src="{{url\(\')'.$fileName.'(\'\)}}\?v=)[0-9]+("></script>)~m';
            if (! preg_match($pattern, $appBladeStr)) {
                $this->error(" -> Localization: Failed to find pattern for script in app.blade.php for file: $fileName");
                $this->info("pattern: $pattern");

                return false;
            }
            $timeAsStr = date('Y-m-d H:i:s', $this->time);
            $appBladeStr = preg_replace_callback($pattern, function ($m) use ($timeAsStr, $fileName) {
                return $m[1].$timeAsStr.$m[2].$fileName.$m[3].$this->time.$m[4];
            }, $appBladeStr);
        }

        file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT));

        return $success && file_put_contents('resources/views/app.blade.php', $appBladeStr);
        // 'export const __trKeys = '.json_encode(array_map(fn ($x) => "__tr_$x", array_keys($langVals))).';';
    }

    private function needsConversion(&$state, $jsFileName, $jsons): bool
    {
        // if force option is set, return true
        if ($this->option('force')) {
            return true;
        }

        // return true;
        $updateTimestamp = false;
        foreach ($jsons as $jsonFile) {
            if (! array_key_exists($jsonFile, $state) || $state[$jsonFile] !== filemtime($jsonFile)) {
                $updateTimestamp = true;
            }
            $state[$jsonFile] = filemtime($jsonFile);
        }

        if (! $updateTimestamp && file_exists("public/$jsFileName")) {
            return false;
        }

        return true;
    }
}

readonly class EnumDef
{
    public function __construct(
        public array $arr,
        public string $enumName,
        public ?string $typeName,
        public bool $pretty = false,
        public bool $asObject = false
    ) {}

    /**
     * @param class-string|ArgBaseEnum $class
     * @return EnumDef
     */
    public static function fromBaseEnum($class){
        return new EnumDef($class::getAll(), last(explode('\\', $class)), null, true);
    }
}

readonly class TrConfig
{
    public string $globPath;

    public string $type;

    public string $jsFileName;

    public function __construct(string $globPath, string $type, string $jsFileName)
    {
        $this->globPath = $globPath;
        $this->type = $type;
        $this->jsFileName = $jsFileName;
    }
}
