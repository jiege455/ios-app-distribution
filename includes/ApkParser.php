<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// APK文件解析器 - 使用 php-apk-parser 第三方库
// 支持多种APK格式的图标提取

// 引入第三方库的自动加载器
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Stream.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/SeekableStream.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Utils.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Xml.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/XmlParser.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/ManifestXmlElement.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Archive.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Config.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/ResourcesParser.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Manifest.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Application.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Activity.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/IntentFilter.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/AndroidPlatform.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Parser.php';

// 引入异常类
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Exceptions/ApkException.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Exceptions/FileNotFoundException.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Exceptions/StreamNotFoundException.php';
require_once __DIR__ . '/../php-apk-parser-master/lib/ApkParser/Exceptions/XmlParserException.php';

// APK解析器类 - 封装第三方库
class ApkParser {
    // 保存解析器实例
    private $parser = null;
    // 保存图标资源路径列表（从resources.arsc解析得到）
    private $iconResourcePaths = array();
    // 保存图标资源名称
    private $iconResourceName = null;
    
    // 解析APK文件
    public function parse($apkPath) {
        // 初始化默认返回值
        $app_info = array(
            'app_name' => pathinfo($apkPath, PATHINFO_FILENAME),
            'version' => '1.0.0',
            'bundle_id' => '',
            'platform' => 'android',
            'icon_path' => ''
        );
        
        try {
            // 使用第三方库解析APK
            $this->parser = new \ApkParser\Parser($apkPath, array('manifest_only' => false));
            
            // 获取manifest对象
            $manifest = $this->parser->getManifest();
            
            // 获取包名
            $app_info['bundle_id'] = $manifest->getPackageName();
            
            // 获取版本号
            try {
                $app_info['version'] = $manifest->getVersionName();
            } catch (Exception $e) {
                $app_info['version'] = (string)$manifest->getVersionCode();
            }
            
            // 获取应用名称
            $application = $manifest->getApplication();
            $label = $application->getLabel();
            
            if (!empty($label)) {
                if (preg_match('/^@?(0x[0-9a-fA-F]+)$/i', $label, $matches)) {
                    $resources = $this->parser->getResources($matches[0]);
                    if (!empty($resources) && is_array($resources)) {
                        $app_info['app_name'] = end($resources);
                    }
                } else {
                    $app_info['app_name'] = $label;
                }
            }
            
            // 获取图标路径
            $icon = $application->getIcon();
            $app_info['icon_path'] = $icon;
            
            // 解析图标资源路径
            $this->resolveIconPaths($icon);
            
            // 如果应用名称仍然为空，尝试从strings.xml获取
            if (empty($app_info['app_name']) || strpos($app_info['app_name'], '@') === 0 || $app_info['app_name'] === pathinfo($apkPath, PATHINFO_FILENAME)) {
                $appName = $this->getAppNameFromStringsXml($apkPath);
                if (!empty($appName)) {
                    $app_info['app_name'] = $appName;
                }
            }
            
        } catch (Exception $e) {
            error_log('APK解析错误: ' . $e->getMessage());
        }
        
        return $app_info;
    }
    
    // 解析图标资源路径
    private function resolveIconPaths($icon) {
        $this->iconResourcePaths = array();
        $this->iconResourceName = 'ic_launcher';
        
        if (empty($icon)) {
            return;
        }
        
        // 如果是资源ID（如 0x7f0d0000）
        if (preg_match('/^@?(0x[0-9a-fA-F]+)$/i', $icon, $matches)) {
            $resourceId = $matches[0];
            $resources = $this->parser ? $this->parser->getResources($resourceId) : false;
            
            if ($resources && is_array($resources)) {
                foreach ($resources as $res) {
                    if (is_string($res)) {
                        $ext = strtolower(pathinfo($res, PATHINFO_EXTENSION));
                        if (in_array($ext, array('png', 'jpg', 'jpeg', 'webp'))) {
                            $this->iconResourcePaths[] = $res;
                        }
                    }
                }
                
                if (!empty($this->iconResourcePaths)) {
                    $basename = basename($this->iconResourcePaths[0]);
                    $nameWithoutExt = preg_replace('/\.[^.]+$/', '', $basename);
                    if (!empty($nameWithoutExt)) {
                        $this->iconResourceName = $nameWithoutExt;
                    }
                }
            }
        } elseif (preg_match('@/(\w+)/(\w+)@', $icon, $matches)) {
            $this->iconResourceName = $matches[2];
        } elseif (preg_match('@^(\w+)$@', $icon)) {
            $this->iconResourceName = $icon;
        }
    }
    
    // 获取图标文件 - 多渠道获取策略
    public function getIconContent($apkPath) {
        $zip = new ZipArchive();
        if ($zip->open($apkPath) !== TRUE) {
            return null;
        }
        
        // 收集所有候选图标
        $candidates = array();
        
        // 渠道1：从resources.arsc解析的路径中查找（最高优先级）
        $candidates = array_merge($candidates, $this->findIconFromResource($zip));
        
        // 渠道2：标准目录查找（res/mipmap, res/drawable）
        $candidates = array_merge($candidates, $this->findIconFromStandardPath($zip));
        
        // 渠道3：混淆目录查找（r/, r0/, r1/ 等）
        $candidates = array_merge($candidates, $this->findIconFromObfuscatedPath($zip));
        
        // 渠道4：全APK遍历查找（最后的备选）
        $candidates = array_merge($candidates, $this->findIconByScanning($zip));
        
        // 去重并排序
        $candidates = $this->deduplicateAndSort($candidates);
        
        // 获取最佳图标的内容
        $content = null;
        if (!empty($candidates)) {
            $best = $candidates[0];
            error_log('选择的图标: ' . $best['filename'] . ', 分数: ' . $best['score'] . ', 大小: ' . $best['size'] . ' bytes, 来源: ' . $best['source']);
            $content = $zip->getFromName($best['filename']);
        }
        
        $zip->close();
        return $content;
    }
    
    // 渠道1：从resources.arsc解析的路径查找
    private function findIconFromResource($zip) {
        $candidates = array();
        
        if (empty($this->iconResourcePaths)) {
            return $candidates;
        }
        
        foreach ($this->iconResourcePaths as $iconPath) {
            // 尝试多种路径格式
            $tryPaths = array(
                $iconPath,                    // 原始路径: r/j/ic_launcher.png
                ltrim($iconPath, '/'),        // 去除前导斜杠
            );
            
            foreach ($tryPaths as $path) {
                $stat = $zip->statName($path);
                if ($stat && $stat['size'] > 500) {
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    if (in_array($ext, array('png', 'jpg', 'jpeg', 'webp'))) {
                        $candidates[] = array(
                            'filename' => $path,
                            'basename' => basename($path, '.' . $ext),
                            'size' => $stat['size'],
                            'score' => 3000, // 最高优先级
                            'source' => 'resource'
                        );
                    }
                }
            }
        }
        
        return $candidates;
    }
    
    // 渠道2：标准目录查找
    private function findIconFromStandardPath($zip) {
        $candidates = array();
        $iconName = $this->iconResourceName ?: 'ic_launcher';
        
        // 标准密度目录
        $densities = array('xxxhdpi', 'xxhdpi', 'xhdpi', 'hdpi', 'mdpi', 'ldpi', 'nodpi');
        $types = array('mipmap', 'drawable');
        
        foreach ($types as $type) {
            foreach ($densities as $density) {
                // 尝试多种文件名
                $fileNames = array(
                    $iconName,
                    'ic_launcher',
                    'app_icon',
                    'icon'
                );
                
                foreach ($fileNames as $fileName) {
                    foreach (array('png', 'webp', 'jpg') as $ext) {
                        $path = "res/{$type}-{$density}/{$fileName}.{$ext}";
                        $stat = $zip->statName($path);
                        if ($stat && $stat['size'] > 500) {
                            $score = $this->calculateStandardScore($type, $density, $fileName, $iconName);
                            $candidates[] = array(
                                'filename' => $path,
                                'basename' => $fileName,
                                'size' => $stat['size'],
                                'score' => $score,
                                'source' => 'standard'
                            );
                        }
                    }
                }
            }
        }
        
        return $candidates;
    }
    
    // 渠道3：混淆目录查找
    private function findIconFromObfuscatedPath($zip) {
        $candidates = array();
        $iconName = $this->iconResourceName ?: 'ic_launcher';
        
        // 常见的混淆目录前缀
        $obfuscatedDirs = array('r', 'r0', 'r1', 'r2', 'r3', 'r4', 'r5', 'r6', 'r7', 'r8', 'r9');
        
        // 遍历APK查找混淆目录下的图标
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, array('png', 'jpg', 'jpeg', 'webp'))) {
                continue;
            }
            
            // 检查是否在混淆目录下
            $isObfuscated = false;
            foreach ($obfuscatedDirs as $dir) {
                if (strpos($filename, $dir . '/') === 0) {
                    $isObfuscated = true;
                    break;
                }
            }
            
            if (!$isObfuscated) {
                continue;
            }
            
            $basename = basename($filename, '.' . $ext);
            $stat = $zip->statName($filename);
            $size = $stat ? $stat['size'] : 0;
            
            if ($size <= 500) {
                continue;
            }
            
            // 排除不需要的
            if ($this->shouldExclude($basename)) {
                continue;
            }
            
            // 计算分数
            $score = 2000; // 混淆目录优先级较高
            if (strtolower($basename) === strtolower($iconName)) {
                $score += 500;
            } elseif (strpos(strtolower($basename), 'launcher') !== false) {
                $score += 300;
            } elseif (strpos(strtolower($basename), 'icon') !== false) {
                $score += 100;
            }
            
            $candidates[] = array(
                'filename' => $filename,
                'basename' => $basename,
                'size' => $size,
                'score' => $score,
                'source' => 'obfuscated'
            );
        }
        
        return $candidates;
    }
    
    // 渠道4：全APK遍历查找
    private function findIconByScanning($zip) {
        $candidates = array();
        $iconName = $this->iconResourceName ?: 'ic_launcher';
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, array('png', 'jpg', 'jpeg', 'webp'))) {
                continue;
            }
            
            $basename = basename($filename, '.' . $ext);
            $stat = $zip->statName($filename);
            $size = $stat ? $stat['size'] : 0;
            
            if ($size <= 500) {
                continue;
            }
            
            // 排除不需要的
            if ($this->shouldExclude($basename)) {
                continue;
            }
            
            // 计算分数
            $score = $this->calculateScanScore($filename, $basename, $iconName);
            
            if ($score > 0) {
                $candidates[] = array(
                    'filename' => $filename,
                    'basename' => $basename,
                    'size' => $size,
                    'score' => $score,
                    'source' => 'scan'
                );
            }
        }
        
        return $candidates;
    }
    
    // 计算标准目录分数
    private function calculateStandardScore($type, $density, $fileName, $targetName) {
        $score = 1000;
        
        // 名称匹配
        if (strtolower($fileName) === strtolower($targetName)) {
            $score += 500;
        } elseif (strpos(strtolower($fileName), 'launcher') !== false) {
            $score += 300;
        } elseif (strpos(strtolower($fileName), 'icon') !== false) {
            $score += 100;
        }
        
        // 类型加分
        if ($type === 'mipmap') {
            $score += 100;
        } else {
            $score += 50;
        }
        
        // 密度加分
        $densityScores = array(
            'xxxhdpi' => 60,
            'xxhdpi' => 50,
            'xhdpi' => 40,
            'hdpi' => 30,
            'mdpi' => 20,
            'ldpi' => 10,
            'nodpi' => 5
        );
        $score += isset($densityScores[$density]) ? $densityScores[$density] : 0;
        
        return $score;
    }
    
    // 计算扫描分数
    private function calculateScanScore($filename, $basename, $targetName) {
        $score = 0;
        $lowerFilename = strtolower($filename);
        $lowerBasename = strtolower($basename);
        $lowerTarget = strtolower($targetName);
        
        // 名称匹配
        if ($lowerBasename === $lowerTarget) {
            $score += 1000;
        } elseif (strpos($lowerBasename, 'launcher') !== false) {
            $score += 500;
        } elseif (strpos($lowerBasename, 'icon') !== false) {
            $score += 200;
        } else {
            return 0; // 不匹配则跳过
        }
        
        // 类型加分
        if (strpos($lowerFilename, 'mipmap') !== false) {
            $score += 100;
        } elseif (strpos($lowerFilename, 'drawable') !== false) {
            $score += 50;
        }
        
        // 密度加分
        if (strpos($lowerFilename, 'xxxhdpi') !== false) {
            $score += 60;
        } elseif (strpos($lowerFilename, 'xxhdpi') !== false) {
            $score += 50;
        } elseif (strpos($lowerFilename, 'xhdpi') !== false) {
            $score += 40;
        } elseif (strpos($lowerFilename, 'hdpi') !== false) {
            $score += 30;
        } elseif (strpos($lowerFilename, 'mdpi') !== false) {
            $score += 20;
        }
        
        return $score;
    }
    
    // 判断是否应该排除
    private function shouldExclude($basename) {
        $excludePatterns = array(
            'round', 'foreground', 'background', 
            'masked', 'monochrome', 'notification',
            'shortcut', 'widget', 'splash', 'banner',
            'tab', 'btn', 'button', 'nav', 'menu'
        );
        
        $lowerBasename = strtolower($basename);
        foreach ($excludePatterns as $pattern) {
            if (strpos($lowerBasename, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    // 去重并排序
    private function deduplicateAndSort($candidates) {
        // 去重
        $unique = array();
        $seen = array();
        foreach ($candidates as $item) {
            if (!isset($seen[$item['filename']])) {
                $seen[$item['filename']] = true;
                $unique[] = $item;
            }
        }
        
        // 排序：先按分数，再按大小
        usort($unique, function($a, $b) {
            if ($a['score'] == $b['score']) {
                return $b['size'] - $a['size'];
            }
            return $b['score'] - $a['score'];
        });
        
        return $unique;
    }
    
    // 从strings.xml获取应用名称
    private function getAppNameFromStringsXml($apkPath) {
        $zip = new ZipArchive();
        if ($zip->open($apkPath) !== TRUE) {
            return '';
        }
        
        $paths = array(
            'res/values/strings.xml',
            'res/values-zh/strings.xml',
            'res/values-zh-rCN/strings.xml',
            'res/values-en/strings.xml',
        );
        
        foreach ($paths as $path) {
            $content = $zip->getFromName($path);
            if ($content !== false) {
                $name = $this->parseStringsXml($content);
                if (!empty($name)) {
                    $zip->close();
                    return $name;
                }
            }
        }
        
        $zip->close();
        return '';
    }
    
    // 解析strings.xml内容
    private function parseStringsXml($content) {
        if (strpos(trim($content), '<?xml') !== 0) {
            return '';
        }
        
        $xml = @simplexml_load_string($content);
        if (!$xml) {
            return '';
        }
        
        foreach ($xml->string as $string) {
            $name = (string)$string['name'];
            if ($name === 'app_name') {
                $value = (string)$string;
                if (!empty($value) && $value !== '@string/app_name' && strpos($value, '@') !== 0) {
                    return $value;
                }
            }
        }
        
        return '';
    }
}
?>
