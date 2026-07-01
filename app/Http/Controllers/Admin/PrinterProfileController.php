<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PrinterProfile;
use App\Models\SheetTemplate;
use App\Services\SheetPrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Printer calibration profiles (super-admin). Each physical printer gets a
 * profile storing measured front/back offsets; PDFs compensate automatically.
 */
class PrinterProfileController extends Controller
{
    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:100'],
            'x_offset_front_mm'   => ['required', 'numeric', 'between:-20,20'],
            'y_offset_front_mm'   => ['required', 'numeric', 'between:-20,20'],
            'x_offset_back_mm'    => ['required', 'numeric', 'between:-20,20'],
            'y_offset_back_mm'    => ['required', 'numeric', 'between:-20,20'],
            'rotation_offset_deg' => ['required', 'numeric', 'between:-5,5'],
            'mirror_back'         => ['boolean'],
            'flip_mode'           => ['required', Rule::in(['long_edge', 'short_edge'])],
            'is_default'          => ['boolean'],
        ];
    }

    public function index()
    {
        // Guarded against schema drift (deploy without migrate) — the page loads
        // with empty lists; the dashboard banner reports the pending migrations.
        return Inertia::render('Admin/Printers/Index', [
            'printers' => Schema::hasTable('printer_profiles')
                ? PrinterProfile::orderBy('name')->get() : collect(),
            'sheets'   => Schema::hasTable('sheet_templates')
                ? SheetTemplate::where('is_active', true)->get(['id', 'name']) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        if (! empty($data['is_default'])) {
            PrinterProfile::query()->update(['is_default' => false]);
        }

        $printer = PrinterProfile::create($data);

        AuditLog::createRecord(null, auth()->id(), 'printer.created', 'printer_profiles', $printer->id, null, ['name' => $printer->name]);

        return back()->with('success', "Printer \"{$printer->name}\" added.");
    }

    public function update(Request $request, PrinterProfile $printerProfile)
    {
        $data = $request->validate($this->rules());

        if (! empty($data['is_default'])) {
            PrinterProfile::whereKeyNot($printerProfile->id)->update(['is_default' => false]);
        }

        $printerProfile->update($data);

        AuditLog::createRecord(null, auth()->id(), 'printer.updated', 'printer_profiles', $printerProfile->id, null, ['name' => $printerProfile->name]);

        return back()->with('success', 'Calibration saved.');
    }

    public function destroy(PrinterProfile $printerProfile)
    {
        $name = $printerProfile->name;
        $printerProfile->delete();

        AuditLog::createRecord(null, auth()->id(), 'printer.deleted', 'printer_profiles', null, null, ['name' => $name]);

        return back()->with('success', "Printer \"{$name}\" removed.");
    }

    /**
     * Calibration test sheet for one side, with this printer's current offsets
     * applied — so re-printing after saving shows the corrected registration.
     */
    public function calibrationSheet(PrinterProfile $printerProfile, SheetTemplate $sheetTemplate, string $side, SheetPrintService $printer)
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $bytes = $printer->renderCalibration(
            $sheetTemplate,
            $side,
            $printerProfile->offsetFor($side),
            $side === 'back' ? $printerProfile->rotation_offset_deg : 0.0,
            $side === 'back' && $printerProfile->mirror_back,
        );

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="calibration-'.$printerProfile->id.'-'.$side.'.pdf"',
        ]);
    }
}
