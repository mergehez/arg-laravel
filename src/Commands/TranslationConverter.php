<?php

namespace Arg\Laravel\Commands;

class TranslationConverter
{
    private mixed $state;

    // private string $targetDir = 'public/lang';

    private mixed $appLocale;

    private array $displayLangKeys;

    private int $time = 0;

    private array $configs;

    public function __construct(
        private readonly ArgTransformer $cmd,
        protected ?string $targetPhpDirectory = 'public/lang',
        protected string $sourceDirectory = 'lang',
        protected string $sourceExt = 'json',
    ) {
        $this->initState();

        if ($this->targetPhpDirectory && !file_exists($this->targetPhpDirectory)) {
            mkdir($this->targetPhpDirectory, 0777, true);
        }

        $this->time = time();
        $this->appLocale = config('app.locale');
        $this->displayLangKeys = $this->cmd->getDisplayLangKeys();
        $this->configs = [
            new TrConfig($sourceDirectory.'/*.'.$this->sourceExt, '', 'translations.js'),
            // new TrConfig('lang/countries/*.json', 'country', 'translations_countries.js'),
            // new TrConfig('lang/languages/*.json', 'language', 'translations_languages.js'),
            // new TrConfig('lang/validation/*.json', 'validation', 'translations_validation.js'),
        ];
    }

    public function convert(): bool
    {

        // $arrNeedConversion = array_fill(0, count($this->configs), false);

        for ($i = 0; $i < count($this->configs); $i++) {
            $trConfig = $this->configs[$i];
            if (!$this->aaa($trConfig)) {
                return false;
            }
        }

        $res = $this->generateTrKeys()
            && $this->updateAppBlade();

        if ($res) {
            $this->updateState();
        }

        return $res;
        // 'export const __trKeys = '.json_encode(array_map(fn ($x) => "__tr_$x", array_keys($langVals))).';';
    }

    private function aaa(TrConfig $trConfig): bool
    {
        $sourceFiles = glob($trConfig->globPath);
        if (!$sourceFiles) {
            $this->cmd->error(" -> Localization: No json files found using pattern: '$trConfig->globPath'");
            exit(1);
        }

        $trConfig->needsConversion = $this->needsConversion($trConfig->jsFileName, $sourceFiles);
        if ($trConfig->needsConversion) {
            $this->cmd->info(" -> Localization: converting json files for pattern: '$trConfig->globPath'");
        } else {
            $this->cmd->info(" -> Localization: No changes detected in json files for pattern: '$trConfig->globPath'");

            return false;
        }

        $langVals = $this->getLangValues($trConfig, $sourceFiles);

        $this->generateJsFiles($langVals, $trConfig);

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    private function getLangValues($trConfig, $sourceFiles): array
    {
        /** @var array<string,mixed> $langVals */
        $langVals = [];
        foreach ($sourceFiles as $sourceFile) {
            if ($this->sourceExt === 'json') {
                $langCode = str_replace('.json', '', basename($sourceFile));
                // dd(str_replace('.json', '', basename($jsonFile)));

                if (!in_array($langCode, $this->displayLangKeys)) {
                    continue;
                }

                $decoded = json_decode(file_get_contents($sourceFile), true);

                if (!array_key_exists($langCode, $langVals)) {
                    $langVals[$langCode] = [];
                }

                foreach ($decoded as $k => $v) {
                    $k = trim($k);
                    $langVals[$langCode][$k] = $v;
                }
            } elseif ($this->sourceExt === 'ts') {
                $tsContent = file_get_contents($sourceFile);
                $tsContent = str_replace('export default ', '', $tsContent);
                $tsContent = explode(' satisfies Record<', $tsContent)[0];
                $tsContent = trim($tsContent, ';');
                $tsContent = preg_replace('/,\s*([]}])/m', '$1', $tsContent);
                $tsContent = trim($tsContent);
                // remove trailing comma
                dump($sourceFile);
                try {
                    $json = json_decode($tsContent, true, flags: JSON_THROW_ON_ERROR); // content of base.ts
                } catch (\Exception $e) {
                    dump($tsContent);
                    dump($e->getMessage());
                }
                foreach ($json as $obj) {
                    foreach ($obj as $langCode => $val) {
                        if (in_array($langCode, $this->displayLangKeys) && !array_key_exists($langCode, $langVals)) {
                            $langVals[$langCode] = [];
                        }
                        break;
                    }
                    break;
                }

                foreach ($json as $key => $obj) {
                    $k = trim($key);
                    foreach ($obj as $langCode => $val) {
                        if (in_array($langCode, $this->displayLangKeys)) {
                            $langVals[$langCode][$k] = $val;
                        }
                    }
                }
            }
        }

        // sort $langVals by key
        foreach ($langVals as $langCode => $val) {
            ksort($langVals[$langCode]);
        }

        if ($this->targetPhpDirectory) {
            foreach ($langVals as $langCode => $val) {
                file_put_contents("$this->targetPhpDirectory/$langCode$trConfig->type.php", "<?php\n\nreturn ".var_export($val, true).';');
            }
        }

        return $langVals;
    }

    /**
     * @param  array<string, mixed>  $langVals
     */
    private function generateJsFiles(array $langVals, TrConfig $trConfig): void
    {
        $lineBreak = ' ';
        $langValsJsStr = '';
        $langValsJsStr2 = '';
        foreach ($langVals as $lang => $vals) {
            $langValsJsStr .= "window.__tr_{$trConfig->type}_$lang = ".json_encode($vals, strlen(trim($lineBreak)) > 0 ? JSON_PRETTY_PRINT : 0).";$lineBreak";
            $langValsJsStr2 .= "export const __tr_{$trConfig->type}_$lang = ".json_encode($vals, strlen(trim($lineBreak)) > 0 ? JSON_PRETTY_PRINT : 0)." as const;$lineBreak";
        }
        file_put_contents('resources/js/Helpers/'.str_replace('.js', '.ts', $trConfig->jsFileName), $langValsJsStr2);

        $langValsJsStr .= 'window.__getByLocale'.ucfirst($trConfig->type).' = (locale) => {'.$lineBreak;
        foreach ($langVals as $lang => $_) {
            $langValsJsStr .= "  if (locale === '$lang') return __tr_{$trConfig->type}_$lang;$lineBreak";
        }
        $langValsJsStr .= "  return __tr_{$trConfig->type}_$this->appLocale;$lineBreak}$lineBreak";

        if (!file_put_contents("public/$trConfig->jsFileName", "$lineBreak$langValsJsStr")) {
            $this->cmd->error(' -> Localization: Failed to write js file: '.$trConfig->jsFileName);

            exit(1);
        }
    }

    private function generateTrKeys(): bool
    {
        $trKeysContent2 = '';
        foreach ($this->configs as $config) {
            $trKeysContent2 .= "import {__tr_{$config->type}_$this->appLocale} from './".str_replace('.js', '', $config->jsFileName)."';\n";
        }
        $trKeysContent2 .= "\n";
        foreach ($this->configs as $config) {
            $trKeysContent2 .= 'export type TrKey'.ucfirst($config->type)." = keyof typeof __tr_{$config->type}_$this->appLocale;\n";
        }

        return (bool) file_put_contents('resources/js/Helpers/tr_keys.ts', $trKeysContent2);
    }

    private function updateAppBlade(): bool
    {
        $appBladeStr = file_get_contents('resources/views/app.blade.php');
        for ($i = 0; $i < count($this->configs); $i++) {
            if (!$this->configs[$i]->needsConversion) {
                continue;
            }
            $fileName = $this->configs[$i]->jsFileName;
            $pattern = '~(<!-- last update )[0-9 .:-]+( -->[\n\s]+<script src="{{url\(\')'.$fileName.'(\'\)}}\?v=)[0-9]+("></script>)~m';
            if (!preg_match($pattern, $appBladeStr)) {
                $this->cmd->error(" -> Localization: Failed to find pattern for script in app.blade.php for file: $fileName");
                $this->cmd->info("pattern: $pattern");

                return false;
            }
            $timeAsStr = date('Y-m-d H:i:s', $this->time);
            $appBladeStr = preg_replace_callback($pattern, function ($m) use ($timeAsStr, $fileName) {
                return $m[1].$timeAsStr.$m[2].$fileName.$m[3].$this->time.$m[4];
            }, $appBladeStr);
        }

        return (bool) file_put_contents('resources/views/app.blade.php', $appBladeStr);
    }

    private function needsConversion($jsFileName, $jsons): bool
    {
        // if force option is set, return true
        if ($this->cmd->option('force')) {
            return true;
        }

        // return true;
        $updateTimestamp = false;
        foreach ($jsons as $jsonFile) {
            if (!array_key_exists($jsonFile, $this->state) || $this->state[$jsonFile] !== filemtime($jsonFile)) {
                $updateTimestamp = true;
            }
            $this->state[$jsonFile] = filemtime($jsonFile);
        }

        if ($updateTimestamp || !file_exists("public/$jsFileName")) {
            return true;
        }
        return false;

    }

    private string $statePath = '.idea/JsonToPhpState.json';

    private function initState(): void
    {

        $this->state = json_decode(file_get_contents($this->statePath), true);
        if (!file_exists($this->statePath)) {
            file_put_contents($this->statePath, '{}');

            return;
        }
        $this->state = json_decode(file_get_contents($this->statePath), true);
    }

    private function updateState(): void
    {
        file_put_contents($this->statePath, json_encode($this->state, JSON_PRETTY_PRINT));
    }
}

class TrConfig
{
    public function __construct(
        public readonly string $globPath,
        public readonly string $type,
        public readonly string $jsFileName,
        public bool $needsConversion = false
    ) {
    }
}
