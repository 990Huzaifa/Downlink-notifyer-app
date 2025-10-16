<?php

namespace App\Console\Commands;

use App\Models\SiteCheck;
use App\Models\SiteLink;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Console\Command;

class Notify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->comment('');

        $Sites = SiteLink::select('site_links.*','site_checks.checked_at')
            ->join('site_checks', 'site_checks.site_link_id', '=', 'site_links.id')
            ->get();
        foreach ($Sites as $Site) {
            $duration = $Site->duration; //30,60,300,1800,3600,43200,86400 in secs
            $last_checked = $Site->checked_at;

            // here we check  last_checked + duration < now
            if( (strtotime($last_checked) + $duration) < time() ) {
                
                $metrics = probe($Site->url, (int)$Site->duration, 15);

                SiteCheck::where('site_link_id', $Site->id)
                    ->update([
                        'status' => $metrics['status'],
                        'response_time_ms' => $metrics['response_time_ms'],
                        'ssl_days_left'    => $metrics['ssl_days_left'],
                        'html_bytes'       => $metrics['html_bytes'],
                        'assets_bytes'       => $metrics['assets_bytes'],
                        'checked_at' => $metrics['last_checked_at'],
                    ]);

                $this->comment("Notification sent for site: {$Site->url}");
                $service = new FirebaseService();
                    $user = User::find($Site->user_id);
                // send notification logic here
                if($metrics['status'] == 'down') {
                    $service->sendToDevice($user->fcm_token, "Site Down Alert", "The site {$Site->url} is down.");
                }else{
                    $service->sendToDevice($user->fcm_token, "Site Up Alert", "The site {$Site->url} is up.");
                }
            } else {
                $this->comment("No notification needed for site: {$Site->url}");
            }
        }
    }
}
