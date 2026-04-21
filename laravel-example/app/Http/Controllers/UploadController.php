<?php

namespace App\Http\Controllers;

use App\Services\ClamAvService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request, ClamAvService $clamAv)
    {
        $request->validate([
            'document' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:5120', // KB = 5MB
            ],
        ]);

        $file = $request->file('document');

        if (!$file || !$file->isValid()) {
            return back()->withErrors([
                'document' => 'Invalid uploaded file.',
            ]);
        }

        $scan = $clamAv->scan($file->getRealPath());

        if (!$scan['ok']) {
            Log::error('ClamAV scanner error', $scan);

            if (config('clamav.fail_closed', true)) {
                return back()->withErrors([
                    'document' => 'Scanner unavailable. Upload blocked for security.',
                ]);
            }
        }

        if ($scan['infected']) {
            Log::warning('Infected file blocked', $scan);

            return back()->withErrors([
                'document' => 'The uploaded file failed the malware scan.',
            ]);
        }

        $path = $file->store('uploads/documents');

        return back()->with('success', 'File uploaded successfully. Path: ' . $path);
    }
}
