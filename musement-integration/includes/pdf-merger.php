<?php
/**
 * Clase para manejar la fusión de PDFs de vouchers
 * 
 * @package Musement_Integration
 */
class Musement_PDF_Merger {


    /**
     * Inicializa las dependencias
     */
    private static function init_libraries() {
        // 🔥 Ruta a las librerías dentro de tu plugin
        $plugin_dir = plugin_dir_path(__FILE__);
        
        // Incluir FPDF (necesario para FPDI)
        require_once $plugin_dir . 'vendor/fpdf/fpdf.php';
        
        // Incluir FPDI (autoloader)
        require_once $plugin_dir . 'vendor/fpdi/src/autoload.php';
        
        // Verificar que FPDI está disponible
        if (!class_exists('setasign\Fpdi\Fpdi')) {
            error_log('[MUSEMENT] ❌ FPDI no encontrado en: ' . $plugin_dir . 'vendor/fpdi/src/');
            return false;
        }
        
        return true;
    }
    
    /**
     * Descarga y fusiona múltiples PDFs en uno solo
     * 
     * @param array $vouchers Array de vouchers con 'url' y 'activity_name'
     * @param string $order_uuid UUID de la orden para nombrar el archivo
     * @return array|false Array con 'path', 'count', 'pages' o false en error
     */
    public static function merge_vouchers($vouchers, $order_uuid) {
        if (!self::init_libraries()) {
            error_log('[MUSEMENT] No se pudieron cargar las librerías necesarias');
            return false;
        }

        error_log('[MUSEMENT] Iniciando fusión de ' . count($vouchers) . ' vouchers');
        
        if (empty($vouchers)) {
            error_log('[MUSEMENT] No hay vouchers para fusionar');
            return false;
        }
        
        // 🔥 Verificar que tenemos URLs válidas
        $valid_vouchers = array_filter($vouchers, function($v) {
            return !empty($v['url']) && filter_var($v['url'], FILTER_VALIDATE_URL);
        });
        
        if (empty($valid_vouchers)) {
            error_log('[MUSEMENT] No hay URLs de vouchers válidas');
            return false;
        }
        
        // Crear directorio temporal
        $temp_dir = self::get_temp_dir();
        if (!$temp_dir) {
            error_log('[MUSEMENT] No se pudo crear directorio temporal');
            return false;
        }
        
        error_log('[MUSEMENT] Directorio temporal: ' . $temp_dir);
        
        $pdf_files = [];
        $downloaded = 0;
        
        // Descargar cada voucher
        foreach ($valid_vouchers as $index => $voucher) {
            error_log("[MUSEMENT] Descargando voucher {$index}: " . $voucher['url']);
            
            $pdf_content = self::download_pdf($voucher['url']);
            if ($pdf_content === false) {
                error_log("[MUSEMENT] ❌ No se pudo descargar voucher {$index}");
                continue;
            }
            
            // Verificar que es un PDF válido
            if (substr($pdf_content, 0, 4) !== '%PDF') {
                error_log("[MUSEMENT] ❌ El contenido no es un PDF válido (voucher {$index})");
                continue;
            }
            
            $temp_file = $temp_dir . "voucher_{$index}_" . uniqid() . ".pdf";
            if (file_put_contents($temp_file, $pdf_content) !== false) {
                $pdf_files[] = $temp_file;
                $downloaded++;
                error_log("[MUSEMENT] ✅ Voucher {$index} descargado correctamente (" . strlen($pdf_content) . " bytes)");
            } else {
                error_log("[MUSEMENT] ❌ No se pudo guardar el voucher {$index}");
            }
        }
        
        if ($downloaded === 0) {
            error_log('[MUSEMENT] ❌ No se pudo descargar ningún voucher');
            self::cleanup_temp_dir($temp_dir);
            return false;
        }
        
        error_log('[MUSEMENT] Descargados ' . $downloaded . ' vouchers correctamente');
        
        // 🔥 Si solo hay un PDF, devolverlo directamente
        if ($downloaded === 1) {
            error_log('[MUSEMENT] Solo un voucher, no es necesario fusionar');
            $result = [
                'path' => $pdf_files[0],
                'count' => 1,
                'pages' => self::count_pages($pdf_files[0])
            ];
            return $result;
        }
        
        // Intentar fusionar con diferentes métodos
        $merged_file = false;
        
        // 🔥 Primero intentar con FPDI
        if (class_exists('setasign\Fpdi\Fpdi')) {
            error_log('[MUSEMENT] Intentando fusión con FPDI');
            $merged_file = self::merge_with_fpdi($pdf_files, $temp_dir, $order_uuid);
        } else {
            error_log('[MUSEMENT] FPDI no está disponible');
        }
        
        // Si FPDI falla, intentar con GhostScript
        if (!$merged_file) {
            error_log('[MUSEMENT] Intentando fusión con GhostScript');
            $merged_file = self::merge_with_ghostscript($pdf_files, $temp_dir, $order_uuid);
        }
        
        // Si todo falla, usar el primer PDF como fallback
        if (!$merged_file) {
            error_log('[MUSEMENT] ⚠️ Fallback: usando solo el primer PDF');
            $merged_file = $temp_dir . "vouchers_merged_{$order_uuid}.pdf";
            if (copy($pdf_files[0], $merged_file)) {
                error_log('[MUSEMENT] ✅ Fallback exitoso');
            } else {
                error_log('[MUSEMENT] ❌ Fallback falló');
                $merged_file = false;
            }
        }
        
        // Limpiar archivos temporales individuales (pero NO el fusionado)
        foreach ($pdf_files as $file) {
            if (file_exists($file) && $file !== $merged_file) {
                unlink($file);
                error_log('[MUSEMENT] Limpiando archivo temporal: ' . basename($file));
            }
        }
        
        if (!$merged_file || !file_exists($merged_file)) {
            error_log('[MUSEMENT] ❌ No se pudo generar el PDF fusionado');
            return false;
        }
        
        $result = [
            'path' => $merged_file,
            'count' => $downloaded,
            'pages' => self::count_pages($merged_file)
        ];
        
        error_log('[MUSEMENT] ✅ Fusión completada: ' . $result['count'] . ' vouchers, ' . $result['pages'] . ' páginas');
        error_log('[MUSEMENT] 📄 Archivo: ' . $result['path']);
        
        return $result;
    }
    
    /**
     * Descarga un PDF desde una URL con mejor manejo de errores
     */
    private static function download_pdf($url) {
        // Añadir user agent para evitar bloqueos
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'stream' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (WordPress Musement Integration)',
                'Accept' => 'application/pdf, */*'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('[MUSEMENT] Error descargando PDF: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log("[MUSEMENT] Error HTTP al descargar PDF: {$status_code}");
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('[MUSEMENT] PDF vacío');
            return false;
        }
        
        return $body;
    }
    
    /**
     * Fusiona PDFs usando FPDI (con mejor manejo de errores)
     */
    private static function merge_with_fpdi($pdf_files, $temp_dir, $order_uuid) {
        // Verificar si FPDI está disponible
        if (!class_exists('setasign\Fpdi\Fpdi')) {
            error_log('[MUSEMENT] FPDI no está disponible');
            return false;
        }
        
        try {
            error_log('[MUSEMENT] Iniciando fusión con FPDI');
            
            $pdf = new \setasign\Fpdi\Fpdi();
            $total_pages = 0;
            
            foreach ($pdf_files as $file) {
                if (!file_exists($file)) {
                    error_log("[MUSEMENT] Archivo no existe: {$file}");
                    continue;
                }
                
                error_log("[MUSEMENT] Procesando archivo: " . basename($file));
                
                try {
                    $page_count = $pdf->setSourceFile($file);
                    $total_pages += $page_count;
                    
                    for ($i = 1; $i <= $page_count; $i++) {
                        $template = $pdf->importPage($i);
                        $size = $pdf->getTemplateSize($template);
                        
                        // Manejar diferentes orientaciones
                        $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                        $pdf->useTemplate($template);
                    }
                    
                    error_log("[MUSEMENT] ✅ Archivo procesado: {$page_count} páginas");
                    
                } catch (Exception $e) {
                    error_log("[MUSEMENT] Error procesando archivo {$file}: " . $e->getMessage());
                    continue;
                }
            }
            
            if ($total_pages === 0) {
                error_log('[MUSEMENT] No se pudo procesar ningún archivo con FPDI');
                return false;
            }
            
            $merged_file = $temp_dir . "vouchers_merged_{$order_uuid}.pdf";
            $pdf->Output('F', $merged_file);
            
            if (file_exists($merged_file) && filesize($merged_file) > 0) {
                error_log("[MUSEMENT] ✅ FPDI completado: {$total_pages} páginas totales");
                return $merged_file;
            }
            
            error_log('[MUSEMENT] FPDI no generó el archivo correctamente');
            return false;
            
        } catch (Exception $e) {
            error_log('[MUSEMENT] Error en FPDI: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fusiona PDFs usando GhostScript (con mejor manejo de errores)
     */
    private static function merge_with_ghostscript($pdf_files, $temp_dir, $order_uuid) {
        $gs_path = self::find_ghostscript();
        if (!$gs_path) {
            error_log('[MUSEMENT] GhostScript no encontrado');
            return false;
        }
        
        error_log("[MUSEMENT] GhostScript encontrado en: {$gs_path}");
        
        $merged_file = $temp_dir . "vouchers_merged_{$order_uuid}.pdf";
        
        // Escapar archivos para línea de comandos
        $escaped_files = array_map('escapeshellarg', $pdf_files);
        $files_str = implode(' ', $escaped_files);
        
        $cmd = sprintf(
            '%s -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile=%s %s 2>&1',
            escapeshellcmd($gs_path),
            escapeshellarg($merged_file),
            $files_str
        );
        
        error_log("[MUSEMENT] Comando GhostScript: {$cmd}");
        
        exec($cmd, $output, $return_code);
        
        if ($return_code === 0 && file_exists($merged_file) && filesize($merged_file) > 0) {
            error_log('[MUSEMENT] ✅ GhostScript completado correctamente');
            return $merged_file;
        }
        
        error_log('[MUSEMENT] GhostScript falló con código: ' . $return_code);
        error_log('[MUSEMENT] Output: ' . implode(' ', $output));
        
        return false;
    }
    
    /**
     * Busca la instalación de GhostScript
     */
    private static function find_ghostscript() {
        // Cache para evitar buscar múltiples veces
        static $gs_path = null;
        
        if ($gs_path !== null) {
            return $gs_path;
        }
        
        $paths = ['gs', 'gswin64c', 'gswin32c'];
        
        foreach ($paths as $path) {
            // Verificar en Linux/Mac
            $result = exec("which {$path} 2>/dev/null");
            if (!empty($result) && is_executable($result)) {
                $gs_path = $result;
                return $gs_path;
            }
        }
        
        // Verificar en Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $possible_paths = [
                'C:\Program Files\gs\gs*\bin\gswin64c.exe',
                'C:\Program Files\gs\gs*\bin\gswin32c.exe',
                'C:\Program Files (x86)\gs\gs*\bin\gswin64c.exe',
                'C:\Program Files (x86)\gs\gs*\bin\gswin32c.exe'
            ];
            
            foreach ($possible_paths as $pattern) {
                $files = glob($pattern);
                if (!empty($files) && is_executable($files[0])) {
                    $gs_path = $files[0];
                    return $gs_path;
                }
            }
        }
        
        $gs_path = false;
        return false;
    }
    
    /**
     * Cuenta las páginas de un PDF
     */
    private static function count_pages($pdf_path) {
        if (!file_exists($pdf_path)) {
            return 0;
        }
        
        // Método simple: buscar "/Type /Page" en el contenido
        $content = file_get_contents($pdf_path);
        if ($content === false) {
            return 0;
        }
        
        // Contar páginas
        $count = substr_count($content, '/Type /Page');
        if ($count > 0) {
            return $count;
        }
        
        // Si no funciona, intentar con GhostScript
        $gs_path = self::find_ghostscript();
        if ($gs_path) {
            $cmd = sprintf(
                '%s -q -dNODISPLAY -dNOPAUSE -dBATCH -sPDFName=%s -c "(\'\') = (PDFName) file pdfinfo.pdf_info pop /PageCount get = quit" 2>&1',
                escapeshellcmd($gs_path),
                escapeshellarg($pdf_path)
            );
            
            $output = exec($cmd);
            if (is_numeric($output) && $output > 0) {
                return intval($output);
            }
        }
        
        return 1; // Si no podemos contar, asumimos 1 página
    }
    
    /**
     * Obtiene un directorio temporal único
     */
    private static function get_temp_dir() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/musement-temp/';
        
        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
            error_log('[MUSEMENT] Directorio base creado: ' . $base_dir);
        }
        
        $temp_dir = $base_dir . uniqid() . '/';
        wp_mkdir_p($temp_dir);
        
        if (file_exists($temp_dir)) {
            error_log('[MUSEMENT] Directorio temporal creado: ' . $temp_dir);
            return $temp_dir;
        }
        
        error_log('[MUSEMENT] No se pudo crear directorio temporal');
        return false;
    }
    
    /**
     * Limpia el directorio temporal
     */
    private static function cleanup_temp_dir($dir) {
        if (!file_exists($dir)) {
            return;
        }
        
        $files = glob($dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                error_log('[MUSEMENT] Eliminando archivo: ' . basename($file));
            }
        }
        
        rmdir($dir);
        error_log('[MUSEMENT] Directorio eliminado: ' . $dir);
    }
}