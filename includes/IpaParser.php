<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// IPA文件解析器，支持解析XML和二进制plist格式

class IpaParser {
    /**
     * 解析IPA文件，提取应用信息
     * @param string $ipa_path IPA文件路径
     * @return array 应用信息数组
     */
    public function parse($ipa_path) {
        $app_info = array(
            'app_name' => 'Unknown App',
            'version' => '1.0.0',
            'bundle_id' => '',
            'platform' => 'ios'
        );
        
        try {
            // 检查文件是否存在
            if (!file_exists($ipa_path)) {
                throw new Exception('IPA文件不存在');
            }
            
            // 检查文件扩展名
            $file_ext = strtolower(pathinfo($ipa_path, PATHINFO_EXTENSION));
            if ($file_ext !== 'ipa') {
                throw new Exception('不是有效的IPA文件');
            }
            
            // 使用ZipArchive直接读取IPA文件
            $zip = new ZipArchive();
            if ($zip->open($ipa_path) !== TRUE) {
                throw new Exception('无法打开IPA文件');
            }
            
            // 查找Info.plist文件
            $plist_content = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (preg_match('#Payload/[^/]+\.app/Info\.plist$#i', $filename)) {
                    $plist_content = $zip->getFromIndex($i);
                    break;
                }
            }
            
            $zip->close();
            
            if ($plist_content === null) {
                throw new Exception('未找到Info.plist文件');
            }
            
            // 解析plist内容
            $parsed = $this->parsePlist($plist_content);
            
            // 提取应用信息
            if (!empty($parsed['CFBundleDisplayName'])) {
                $app_info['app_name'] = $parsed['CFBundleDisplayName'];
            } elseif (!empty($parsed['CFBundleName'])) {
                $app_info['app_name'] = $parsed['CFBundleName'];
            }
            
            if (!empty($parsed['CFBundleShortVersionString'])) {
                $app_info['version'] = $parsed['CFBundleShortVersionString'];
            } elseif (!empty($parsed['CFBundleVersion'])) {
                $app_info['version'] = $parsed['CFBundleVersion'];
            }
            
            if (!empty($parsed['CFBundleIdentifier'])) {
                $app_info['bundle_id'] = $parsed['CFBundleIdentifier'];
            }
            
        } catch (Exception $e) {
            // 解析失败时使用默认值
        }
        
        return $app_info;
    }
    
    /**
     * 解析plist文件内容（支持XML和二进制格式）
     * @param string $content plist文件内容
     * @return array 解析后的数组
     */
    private function parsePlist($content) {
        $result = array();
        
        // 检测plist格式
        $header = substr($content, 0, 8);
        
        if ($header === 'bplist00') {
            // 二进制plist格式
            $result = $this->parseBinaryPlist($content);
        } elseif (strpos(trim($content), '<?xml') === 0 || strpos(trim($content), '<!DOCTYPE plist') === 0) {
            // XML plist格式
            $result = $this->parseXmlPlist($content);
        } else {
            // 尝试使用正则表达式提取
            $result = $this->parsePlistWithRegex($content);
        }
        
        return $result;
    }
    
    /**
     * 解析XML格式的plist
     * @param string $content XML内容
     * @return array 解析后的数组
     */
    private function parseXmlPlist($content) {
        $result = array();
        
        // 首先尝试使用正则表达式提取关键信息（更可靠）
        $result = $this->parsePlistWithRegex($content);
        
        // 如果正则提取不完整，尝试XML解析
        if (empty($result['CFBundleIdentifier'])) {
            $xml = @simplexml_load_string($content);
            if ($xml) {
                $xml_result = $this->parseXmlDict($xml);
                $result = array_merge($result, $xml_result);
            }
        }
        
        return $result;
    }
    
    /**
     * 解析XML中的dict节点
     * @param SimpleXMLElement $xml XML对象
     * @return array 解析后的数组
     */
    private function parseXmlDict($xml) {
        $result = array();
        
        // 查找所有dict节点
        $dicts = $xml->xpath('//dict');
        if (empty($dicts)) {
            $dicts = array($xml->dict);
        }
        
        foreach ($dicts as $dict) {
            if (!$dict) continue;
            
            $children = $dict->children();
            $key = null;
            
            foreach ($children as $child) {
                if ($child->getName() === 'key') {
                    $key = (string)$child;
                } elseif ($key !== null) {
                    $value = $this->getXmlValue($child);
                    if (!isset($result[$key])) {
                        $result[$key] = $value;
                    }
                    $key = null;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 获取XML节点的值
     * @param SimpleXMLElement $element XML元素
     * @return mixed 值
     */
    private function getXmlValue($element) {
        $name = $element->getName();
        
        switch ($name) {
            case 'string':
                return (string)$element;
            case 'integer':
                return (int)$element;
            case 'real':
                return (float)$element;
            case 'true':
                return true;
            case 'false':
                return false;
            case 'array':
                $arr = array();
                foreach ($element->children() as $child) {
                    $arr[] = $this->getXmlValue($child);
                }
                return $arr;
            case 'dict':
                return $this->parseXmlDict($element);
            default:
                return (string)$element;
        }
    }
    
    /**
     * 使用正则表达式解析plist
     * @param string $content plist内容
     * @return array 解析后的数组
     */
    private function parsePlistWithRegex($content) {
        $result = array();
        
        // 定义需要提取的键
        $keys = array(
            'CFBundleDisplayName',
            'CFBundleName',
            'CFBundleShortVersionString',
            'CFBundleVersion',
            'CFBundleIdentifier',
            'MinimumOSVersion',
            'UIRequiredDeviceCapabilities'
        );
        
        foreach ($keys as $key) {
            // 尝试匹配 <key>xxx</key> 后跟 <string>yyy</string>
            $pattern = '/<key>' . preg_quote($key, '/') . '<\/key>\s*<string>([^<]*)<\/string>/i';
            if (preg_match($pattern, $content, $matches)) {
                $result[$key] = trim($matches[1]);
                continue;
            }
            
            // 尝试匹配 <key>xxx</key> 后跟其他值类型
            $pattern = '/<key>' . preg_quote($key, '/') . '<\/key>\s*<(integer|real|true|false)>([^<]*)<\/\1>/i';
            if (preg_match($pattern, $content, $matches)) {
                if ($matches[1] === 'integer') {
                    $result[$key] = (int)$matches[2];
                } elseif ($matches[1] === 'real') {
                    $result[$key] = (float)$matches[2];
                } elseif ($matches[1] === 'true') {
                    $result[$key] = true;
                } elseif ($matches[1] === 'false') {
                    $result[$key] = false;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 解析二进制plist格式
     * @param string $content 二进制内容
     * @return array 解析后的数组
     */
    private function parseBinaryPlist($content) {
        $result = array();
        
        try {
            // 解析二进制plist
            $object = $this->parseBplist($content);
            
            if (is_array($object)) {
                // 提取关键信息
                $keys_to_extract = array(
                    'CFBundleDisplayName',
                    'CFBundleName',
                    'CFBundleShortVersionString',
                    'CFBundleVersion',
                    'CFBundleIdentifier'
                );
                
                foreach ($keys_to_extract as $key) {
                    if (isset($object[$key])) {
                        $result[$key] = $object[$key];
                    }
                }
            }
        } catch (Exception $e) {
            // 二进制解析失败，返回空数组
        }
        
        return $result;
    }
    
    /**
     * 解析bplist格式
     * @param string $content 二进制内容
     * @return mixed 解析后的对象
     */
    private function parseBplist($content) {
        $length = strlen($content);
        
        // 读取尾部（32字节）
        if ($length < 32) {
            throw new Exception('Invalid bplist');
        }
        
        $tail = substr($content, -32);
        
        // 解析尾部信息
        $offset_int_size = ord($tail[6]);
        $offset_ref_size = ord($tail[7]);
        $num_objects = $this->unpackInt64(substr($tail, 8, 8));
        $top_object = $this->unpackInt64(substr($tail, 16, 8));
        $offset_table_offset = $this->unpackInt64(substr($tail, 24, 8));
        
        // 读取偏移表
        $offset_table = array();
        for ($i = 0; $i < $num_objects; $i++) {
            $pos = $offset_table_offset + ($i * $offset_int_size);
            $offset_table[] = $this->unpackInt(substr($content, $pos, $offset_int_size), $offset_int_size);
        }
        
        // 解析根对象
        return $this->parseBplistObject($content, $offset_table, $top_object);
    }
    
    /**
     * 解析bplist对象
     * @param string $content 二进制内容
     * @param array $offset_table 偏移表
     * @param int $index 对象索引
     * @return mixed 解析后的对象
     */
    private function parseBplistObject($content, $offset_table, $index) {
        if (!isset($offset_table[$index])) {
            return null;
        }
        
        $offset = $offset_table[$index];
        
        if ($offset >= strlen($content)) {
            return null;
        }
        
        $header = ord($content[$offset]);
        $type = $header & 0xF0;
        $size = $header & 0x0F;
        
        switch ($type) {
            case 0x00: // null, bool, fill
                switch ($header) {
                    case 0x00: return null;
                    case 0x08: return false;
                    case 0x09: return true;
                    default: return null;
                }
                
            case 0x10: // int
                $int_size = 1 << $size;
                return $this->unpackInt(substr($content, $offset + 1, $int_size), $int_size);
                
            case 0x20: // real
                $real_size = 1 << $size;
                if ($real_size === 4) {
                    return unpack('f', substr($content, $offset + 1, 4))[1];
                } elseif ($real_size === 8) {
                    return unpack('d', substr($content, $offset + 1, 8))[1];
                }
                return 0.0;
                
            case 0x30: // date
                return substr($content, $offset + 1, 8);
                
            case 0x40: // data
                if ($size === 0x0F) {
                    $size = $this->readSize($content, $offset + 1);
                    $data_offset = $this->getSizeBytes($size) + 1;
                } else {
                    $data_offset = 1;
                }
                return substr($content, $offset + $data_offset, $size);
                
            case 0x50: // string (ASCII)
                if ($size === 0x0F) {
                    $size = $this->readSize($content, $offset + 1);
                    $data_offset = $this->getSizeBytes($size) + 1;
                } else {
                    $data_offset = 1;
                }
                return substr($content, $offset + $data_offset, $size);
                
            case 0x60: // string (UTF-16)
                if ($size === 0x0F) {
                    $size = $this->readSize($content, $offset + 1);
                    $data_offset = $this->getSizeBytes($size) + 1;
                } else {
                    $data_offset = 1;
                }
                $str = substr($content, $offset + $data_offset, $size * 2);
                return mb_convert_encoding($str, 'UTF-8', 'UTF-16BE');
                
            case 0xA0: // array
                if ($size === 0x0F) {
                    $size = $this->readSize($content, $offset + 1);
                    $ref_offset = $this->getSizeBytes($size) + 1;
                } else {
                    $ref_offset = 1;
                }
                
                $array = array();
                for ($i = 0; $i < $size; $i++) {
                    $ref = $this->unpackInt(substr($content, $offset + $ref_offset + ($i * 1), 1), 1);
                    $array[] = $this->parseBplistObject($content, $offset_table, $ref);
                }
                return $array;
                
            case 0xC0: // set
            case 0xD0: // dict
                if ($size === 0x0F) {
                    $size = $this->readSize($content, $offset + 1);
                    $ref_offset = $this->getSizeBytes($size) + 1;
                } else {
                    $ref_offset = 1;
                }
                
                $dict = array();
                $ref_size = 1; // 简化处理
                
                for ($i = 0; $i < $size; $i++) {
                    $key_ref = $this->unpackInt(substr($content, $offset + $ref_offset + ($i * $ref_size), $ref_size), $ref_size);
                    $val_ref = $this->unpackInt(substr($content, $offset + $ref_offset + ($size * $ref_size) + ($i * $ref_size), $ref_size), $ref_size);
                    
                    $key = $this->parseBplistObject($content, $offset_table, $key_ref);
                    $val = $this->parseBplistObject($content, $offset_table, $val_ref);
                    
                    if (is_string($key)) {
                        $dict[$key] = $val;
                    }
                }
                return $dict;
                
            default:
                return null;
        }
    }
    
    /**
     * 读取大小值
     * @param string $content 内容
     * @param int $offset 偏移
     * @return int 大小
     */
    private function readSize($content, $offset) {
        $size_type = ord($content[$offset]);
        if ($size_type === 0x10) {
            return ord($content[$offset + 1]);
        } elseif ($size_type === 0x11) {
            return unpack('n', substr($content, $offset + 1, 2))[1];
        } elseif ($size_type === 0x12) {
            return unpack('N', substr($content, $offset + 1, 4))[1];
        } elseif ($size_type === 0x13) {
            return $this->unpackInt64(substr($content, $offset + 1, 8));
        }
        return 0;
    }
    
    /**
     * 获取大小值所需的字节数
     * @param int $size 大小
     * @return int 字节数
     */
    private function getSizeBytes($size) {
        if ($size < 256) return 2;
        if ($size < 65536) return 3;
        return 5;
    }
    
    /**
     * 解包整数
     * @param string $data 数据
     * @param int $size 大小
     * @return int 整数
     */
    private function unpackInt($data, $size) {
        if ($size === 1) {
            return ord($data);
        } elseif ($size === 2) {
            return unpack('n', $data)[1];
        } elseif ($size === 4) {
            return unpack('N', $data)[1];
        } elseif ($size === 8) {
            return $this->unpackInt64($data);
        }
        return 0;
    }
    
    /**
     * 解包64位整数
     * @param string $data 数据
     * @return int 整数
     */
    private function unpackInt64($data) {
        $result = unpack('N2', $data);
        return ($result[1] << 32) | $result[2];
    }
}
?>
