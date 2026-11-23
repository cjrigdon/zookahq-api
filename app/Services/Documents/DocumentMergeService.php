<?php

namespace App\Services\Documents;

use App\Mail\DocumentMail;
use App\Models\Booking\Account;
use App\Models\Booking\Contact;
use App\Models\Booking\Contract;
use App\Models\Documents\Document;
use App\Models\Documents\DocumentTemplate;
use App\Models\Events\Event;
use App\Models\TenantSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DocumentMergeService
{
    /**
     * @return list<array{token: string, label: string, group: string}>
     */
    public static function catalog(): array
    {
        $fields = [
            ['group' => 'Account', 'token' => 'account.name', 'label' => 'Account name'],
            ['group' => 'Account', 'token' => 'account.email', 'label' => 'Account email'],
            ['group' => 'Account', 'token' => 'account.phone', 'label' => 'Account phone'],
            ['group' => 'Account', 'token' => 'account.website', 'label' => 'Website'],
            ['group' => 'Account', 'token' => 'account.type', 'label' => 'Account type'],
            ['group' => 'Account', 'token' => 'account.billing_address', 'label' => 'Address'],
            ['group' => 'Account', 'token' => 'account.address_2', 'label' => 'Address 2'],
            ['group' => 'Account', 'token' => 'account.city', 'label' => 'City'],
            ['group' => 'Account', 'token' => 'account.state', 'label' => 'State'],
            ['group' => 'Account', 'token' => 'account.postal_code', 'label' => 'Postal code'],
            ['group' => 'Account', 'token' => 'account.country', 'label' => 'Country'],
            ['group' => 'Contact', 'token' => 'contact.first_name', 'label' => 'First name'],
            ['group' => 'Contact', 'token' => 'contact.last_name', 'label' => 'Last name'],
            ['group' => 'Contact', 'token' => 'contact.full_name', 'label' => 'Full name'],
            ['group' => 'Contact', 'token' => 'contact.email', 'label' => 'Email'],
            ['group' => 'Contact', 'token' => 'contact.phone', 'label' => 'Phone'],
            ['group' => 'Contact', 'token' => 'contact.title', 'label' => 'Title'],
            ['group' => 'Event', 'token' => 'event.name', 'label' => 'Event name'],
            ['group' => 'Event', 'token' => 'event.date', 'label' => 'Event date'],
            ['group' => 'Event', 'token' => 'event.type', 'label' => 'Event type'],
            ['group' => 'Event', 'token' => 'event.status', 'label' => 'Status'],
            ['group' => 'Event', 'token' => 'event.purpose', 'label' => 'Purpose'],
            ['group' => 'Event', 'token' => 'event.description', 'label' => 'Description'],
            ['group' => 'Event', 'token' => 'event.starts_at', 'label' => 'Starts at'],
            ['group' => 'Event', 'token' => 'event.ends_at', 'label' => 'Ends at'],
            ['group' => 'Event', 'token' => 'event.load_in_at', 'label' => 'Load in'],
            ['group' => 'Event', 'token' => 'event.load_out_at', 'label' => 'Load out'],
            ['group' => 'Event', 'token' => 'event.venue_space', 'label' => 'Venue space'],
            ['group' => 'Event', 'token' => 'event.expected_attendance', 'label' => 'Expected attendance'],
            ['group' => 'Event', 'token' => 'event.value', 'label' => 'Value'],
            ['group' => 'Event', 'token' => 'event.client_venue_budget', 'label' => 'Client venue budget'],
            ['group' => 'Event', 'token' => 'event.alcohol_served', 'label' => 'Alcohol served'],
            ['group' => 'Event', 'token' => 'event.planner_name', 'label' => 'Planner name'],
            ['group' => 'Event', 'token' => 'event.planner_title', 'label' => 'Planner title'],
            ['group' => 'Event', 'token' => 'event.planner_organization', 'label' => 'Planner organization'],
            ['group' => 'Event', 'token' => 'event.planner_email', 'label' => 'Planner email'],
            ['group' => 'Event', 'token' => 'event.planner_phone', 'label' => 'Planner phone'],
            ['group' => 'Event', 'token' => 'event.referral', 'label' => 'Referral'],
            ['group' => 'Contract', 'token' => 'contract.title', 'label' => 'Contract title'],
            ['group' => 'Contract', 'token' => 'contract.amount', 'label' => 'Amount'],
            ['group' => 'Contract', 'token' => 'contract.status', 'label' => 'Status'],
            ['group' => 'Contract', 'token' => 'contract.signed_by_name', 'label' => 'Signed by'],
            ['group' => 'System', 'token' => 'tenant.name', 'label' => 'Venue name'],
            ['group' => 'System', 'token' => 'today', 'label' => 'Today (Y-m-d)'],
            ['group' => 'System', 'token' => 'today_long', 'label' => 'Today (long)'],
        ];

        return $fields;
    }

    public function generate(
        DocumentTemplate $template,
        Model $documentable,
        ?Contract $contract = null,
        ?string $title = null,
    ): Document {
        $type = Documentables::typeFor($documentable);
        $values = $this->valuesFor($documentable, $contract);

        $disk = Storage::disk('local');
        $source = $disk->path($template->disk_path);
        if (! is_file($source)) {
            throw new RuntimeException('Template file is missing.');
        }

        $filename = Str::slug($title ?: $template->name).'-'.now()->format('Ymd-His').'.pdf';
        $relative = 'documents/generated/'.Str::uuid().'/'.$filename;
        $disk->makeDirectory(dirname($relative));
        $destination = $disk->path($relative);

        if ($template->isMergeable()) {
            $workDir = sys_get_temp_dir().'/doc-merge-'.Str::uuid();
            mkdir($workDir, 0700, true);
            $working = $workDir.'/source.docx';
            $merged = $workDir.'/merged.docx';
            try {
                copy($source, $working);
                $this->normalizeMergeSyntax($working);
                $processor = new TemplateProcessor($working);
                foreach ($values as $token => $value) {
                    $processor->setValue($token, $value);
                }
                $processor->saveAs($merged);
                $this->convertDocxToPdf($merged, $destination);
            } finally {
                $this->removeDirectory($workDir);
            }
        } else {
            copy($source, $destination);
        }

        $document = Document::create([
            'document_template_id' => $template->id,
            'documentable_type' => $type,
            'documentable_id' => $documentable->getKey(),
            'contract_id' => $contract?->id,
            'title' => $title ?: $template->name,
            'original_filename' => $filename,
            'disk_path' => $relative,
            'mime_type' => 'application/pdf',
            'file_size' => is_file($destination) ? filesize($destination) : null,
            'status' => Document::STATUS_GENERATED,
            'merge_snapshot' => $values,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        if ($contract) {
            $contract->update([
                'document_template_id' => $template->id,
                'document_id' => $document->id,
                'document_path' => $relative,
                'updated_by' => auth()->id(),
            ]);
        }

        return $document->load(['template', 'creator']);
    }

    /**
     * @return array<string, string>
     */
    public function valuesFor(Model $documentable, ?Contract $contract = null): array
    {
        $account = null;
        $contact = null;
        $event = null;

        if ($documentable instanceof Event) {
            $event = $documentable;
            $account = $event->account;
            $contact = $event->contact;
            if (! $contact && $account) {
                $contact = $account->contacts->firstWhere('is_primary', true) ?? $account->contacts->first();
            }
        } elseif ($documentable instanceof Account) {
            $account = $documentable;
            $contact = $account->contacts->firstWhere('is_primary', true) ?? $account->contacts->first();
        } elseif ($documentable instanceof Contact) {
            $contact = $documentable;
            $account = $contact->account;
        }

        $tenantName = TenantSetting::query()->value('company_name') ?: tenant('name');

        $raw = [
            'account.name' => $account?->name,
            'account.email' => $account?->email,
            'account.phone' => $account?->phone,
            'account.website' => $account?->website,
            'account.type' => $account?->type,
            'account.billing_address' => $account?->billing_address,
            'account.address_2' => $account?->address_2,
            'account.city' => $account?->city,
            'account.state' => $account?->state,
            'account.postal_code' => $account?->postal_code,
            'account.country' => $account?->country,
            'contact.first_name' => $contact?->first_name,
            'contact.last_name' => $contact?->last_name,
            'contact.full_name' => trim(($contact?->first_name ?? '').' '.($contact?->last_name ?? '')),
            'contact.email' => $contact?->email,
            'contact.phone' => $contact?->phone,
            'contact.title' => $contact?->title,
            'event.name' => $event?->name,
            'event.date' => $this->formatDateOnly($event?->starts_at),
            'event.type' => $event?->eventType?->name,
            'event.status' => $event?->status,
            'event.purpose' => $event?->purpose,
            'event.description' => $event?->description,
            'event.starts_at' => $this->formatDate($event?->starts_at),
            'event.ends_at' => $this->formatDate($event?->ends_at),
            'event.load_in_at' => $this->formatDate($event?->load_in_at),
            'event.load_out_at' => $this->formatDate($event?->load_out_at),
            'event.venue_space' => $event?->venue_space,
            'event.expected_attendance' => $event?->expected_attendance,
            'event.value' => $this->formatMoney($event?->value),
            'event.client_venue_budget' => $this->formatMoney($event?->client_venue_budget),
            'event.alcohol_served' => $event ? ($event->alcohol_served ? 'Yes' : 'No') : '',
            'event.planner_name' => $event?->planner_name,
            'event.planner_title' => $event?->planner_title,
            'event.planner_organization' => $event?->planner_organization,
            'event.planner_email' => $event?->planner_email,
            'event.planner_phone' => $event?->planner_phone,
            'event.referral' => $event?->referral,
            'contract.title' => $contract?->title,
            'contract.amount' => $this->formatMoney($contract?->amount),
            'contract.status' => $contract?->status,
            'contract.signed_by_name' => $contract?->signed_by_name,
            'tenant.name' => $tenantName,
            'today' => now()->toDateString(),
            'today_long' => now()->format('F j, Y'),
        ];

        $values = [];
        foreach ($raw as $token => $value) {
            $string = $value === null ? '' : (string) $value;
            $values[$token] = $string;
            $values[str_replace('.', '_', $token)] = $string;
        }

        return $values;
    }

    public function send(Document $document, Model $documentable, ?string $to = null, ?string $subject = null, ?string $body = null): Document
    {
        $to = $to ?: $this->defaultRecipient($documentable);
        if (! $to) {
            throw ValidationException::withMessages([
                'to' => 'No email address is available. Enter a recipient.',
            ]);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($document->disk_path)) {
            throw new RuntimeException('Document file is missing.');
        }

        $subject = $subject ?: $document->title;
        $body = $body ?: "Please find {$document->title} attached.";

        Mail::to($to)->send(new DocumentMail(
            $document,
            $subject,
            $body,
            $disk->path($document->disk_path),
        ));

        $document->update([
            'status' => Document::STATUS_SENT,
            'emailed_to' => $to,
            'emailed_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        if ($document->contract_id) {
            $document->loadMissing('contract');
            if ($document->contract && $document->contract->status === 'draft') {
                $document->contract->update([
                    'status' => 'sent',
                    'updated_by' => auth()->id(),
                ]);
            }
        }

        return $document->fresh(['template', 'creator', 'contract']);
    }

    public function defaultRecipient(Model $documentable): ?string
    {
        if ($documentable instanceof Event) {
            return $documentable->contact?->email
                ?: $documentable->planner_email
                ?: $documentable->account?->email;
        }
        if ($documentable instanceof Account) {
            $primary = $documentable->contacts->firstWhere('is_primary', true) ?? $documentable->contacts->first();

            return $documentable->email ?: $primary?->email;
        }
        if ($documentable instanceof Contact) {
            return $documentable->email ?: $documentable->account?->email;
        }

        return null;
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('M j, Y g:i A');
        }

        return (string) $value;
    }

    private function formatDateOnly(mixed $value): string
    {
        if (! $value) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('F j, Y');
        }

        return substr((string) $value, 0, 10);
    }

    private function formatMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return '$'.number_format((float) $value, 2);
    }

    private function convertDocxToPdf(string $docxPath, string $pdfPath): void
    {
        $binary = $this->sofficeBinary();
        if (! $binary) {
            throw new RuntimeException('PDF conversion requires LibreOffice (soffice) on the server.');
        }

        $workDir = sys_get_temp_dir().'/lo-convert-'.Str::uuid();
        mkdir($workDir, 0700, true);
        $home = $workDir.'/home';
        mkdir($home, 0700, true);
        $input = $workDir.'/source.docx';
        copy($docxPath, $input);

        $process = new Process(
            [
                $binary,
                '--headless',
                '--nologo',
                '--nofirststartwizard',
                '--norestore',
                '--convert-to',
                'pdf:writer_pdf_Export',
                '--outdir',
                $workDir,
                $input,
            ],
            $workDir,
            [
                'HOME' => $home,
                'LANG' => 'en_US.UTF-8',
                'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            ],
            null,
            90,
        );
        $process->run();

        $produced = $workDir.'/source.pdf';
        if (! $process->isSuccessful() || ! is_file($produced)) {
            $this->removeDirectory($workDir);
            throw new RuntimeException(
                'Unable to convert the document to PDF.'.
                ($process->getErrorOutput() !== '' ? ' '.$process->getErrorOutput() : ''),
            );
        }

        if (! rename($produced, $pdfPath)) {
            $this->removeDirectory($workDir);
            throw new RuntimeException('Unable to save the generated PDF.');
        }

        $this->removeDirectory($workDir);
    }

    private function sofficeBinary(): ?string
    {
        foreach (['soffice', 'libreoffice'] as $name) {
            $process = Process::fromShellCommandline('command -v '.escapeshellarg($name));
            $process->run();
            $path = trim($process->getOutput());
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }

        foreach ([
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/usr/lib/libreoffice/program/soffice',
        ] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path.DIRECTORY_SEPARATOR.$item;
            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }

    private function normalizeMergeSyntax(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || ! str_ends_with($name, '.xml')) {
                continue;
            }
            $xml = $zip->getFromIndex($i);
            if (! is_string($xml)) {
                continue;
            }
            $updated = preg_replace_callback(
                '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/',
                fn (array $match) => '${'.$match[1].'}',
                $xml,
            );
            if (is_string($updated) && $updated !== $xml) {
                $zip->addFromString($name, $updated);
            }
        }

        $zip->close();
    }
}
