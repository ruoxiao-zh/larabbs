<?php

namespace App\Http\Controllers;

use PDF;
use File;
use Zipper;
use Storage;
use App\Models\Topic;
use Illuminate\Http\Request;

class ZipController extends Controller
{
    public function index()
    {
        $logs = File::files(storage_path('logs'));
        return view('zip', compact('logs'));
    }

    public function download(Request $request)
    {
        if (app()->isLocal()) {
            config(['sudosu.enable' => false]);
        }

        // 打包文件名
        $name = 'pdfs-' . time() . '.zip';
        // 在 Zip 文件中创建 logs 目录
        $zipper = Zipper::make($name)->folder('pdfs');

//        foreach($request->logs as $log) {
//            // 检查提交的文件是否存在
//            $path = storage_path('logs/'.basename($log));
//            if (!File::exists($path)) {
//                continue;
//            }

        $topics = Topic::where('id', '<', 10)->get();

        $temporary_path = storage_path('tmp');
        $save_path      = storage_path('pdf/');
        $pdf_files = [];

        foreach ($topics as $topic) {
            $pdf_file = $save_path . 'topics-' . $topic->id . '.pdf';

            PDF::loadView('topics.show', compact('topic'))
                ->setPaper('a4')
                ->setOption('run-script', true)
                ->setOption('javascript-delay', 1600)//javascript执行等待时间|毫秒
                ->setOption('no-stop-slow-scripts', true)
                ->setTemporaryFolder($temporary_path)
                ->save($pdf_file);

            if (File::exists($pdf_file)) {
                // 将文件加入 zip 包
                $zipper->add($pdf_file);
                $pdf_files[] = $pdf_file;
            }
        }

        // 关闭zip，一定要调用
        $zipper->close();

//        $this->deleteDir($save_path);
        foreach ($pdf_files as $pdf_file) {
            if (File::exists($pdf_file)) {
                unlink($pdf_file);
            }
        }

//        unlink(public_path() . '/topics-1.pdf' );

        // 返回下载响应，下载完成后删除文件
        return response()->download(public_path($name))->deleteFileAfterSend(true);
    }

    public function deleteDir($dir)
    {
        if ( !$handle = @opendir($dir)) {
            return false;
        }

        while (false !== ($file = readdir($handle))) {
            if ($file !== "." && $file !== "..") {       // 排除当前目录与父级目录
                $file = $dir . '/' . $file;
                if (is_dir($file)) {
                    deleteDir($file);
                } else {
                    @unlink($file);
                }
            }

        }
        @rmdir($dir);
    }
}
