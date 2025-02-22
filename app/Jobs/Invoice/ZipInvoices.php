<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Invoice;

use App\Jobs\Entity\CreateEntityPdf;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\UnlinkFile;
use App\Libraries\MultiDB;
use App\Mail\DownloadInvoices;
use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ZipInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoices;

    private $company;

    private $user;

    public $settings;

    public $tries = 1;

    /**
     * @param $invoices
     * @param Company $company
     * @param $email
     * @deprecated confirm to be deleted
     * Create a new job instance.
     */
    public function __construct($invoices, Company $company, User $user)
    {
        $this->invoices = $invoices;

        $this->company = $company;

        $this->user = $user;

        $this->settings = $company->settings;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        MultiDB::setDb($this->company->db);

        // create new zip object
        $zipFile = new \PhpZip\ZipFile();
        $file_name = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.invoices')).'.zip';
        $invitation = $this->invoices->first()->invitations->first();
        $path = $this->invoices->first()->client->invoice_filepath($invitation);

        $this->invoices->each(function ($invoice) {
            (new CreateEntityPdf($invoice->invitations()->first()))->handle();
            if ($invoice->client->getSetting('enable_e_invoice')){
                (new CreateEInvoice($invoice, false))->handle();
            }
        });

        try {

            foreach ($this->invoices as $invoice) {
                $file = $invoice->service()->getInvoicePdf();
                $zip_file_name = basename($file);
                $zipFile->addFromString($zip_file_name, Storage::get($file));

                if($invoice->client->getSetting('enable_e_invoice')){

                    $xinvoice = $invoice->service()->getEInvoice();
                    $xinvoice_zip_file_name = basename($xinvoice);
                    $zipFile->addFromString($xinvoice_zip_file_name, Storage::get($xinvoice));

                }
            }

            Storage::put($path.$file_name, $zipFile->outputAsString());

            $nmo = new NinjaMailerObject;
            $nmo->mailable = new DownloadInvoices(Storage::url($path.$file_name), $this->company);
            $nmo->to_user = $this->user;
            $nmo->settings = $this->settings;
            $nmo->company = $this->company;

            NinjaMailerJob::dispatch($nmo);

            UnlinkFile::dispatch(config('filesystems.default'), $path.$file_name)->delay(now()->addHours(1));
        } catch (\PhpZip\Exception\ZipException $e) {
            nlog('could not make zip => '.$e->getMessage());
        } finally {
            $zipFile->close();
        }
    }
}
