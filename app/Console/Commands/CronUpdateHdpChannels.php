<?php

namespace App\Console\Commands;

use App\Models\Channel;
use Illuminate\Console\Command;
use App\Models\HdpChannel;
use Log;

class CronUpdateHdpChannels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:UpdateHdpChannels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $url = 'http://www.hdplive.net/channellist.xml';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url);
            $htmlContent = $response->getBody()->getContents();
            $channel_list = new \SimpleXMLElement($htmlContent);
            foreach ($channel_list->class as $class) {
                $channelType = $this->xmlAttribute($class, '节目分类');
                foreach($class->channel as $channel) {
                    $channelNum = $this->xmlAttribute($channel, '频道号');
                    $channelName = $this->xmlAttribute($channel, '频道名称');
                    $this->info($channelName."\t".$channelNum);
                    HdpChannel::updateOrCreate(
                        ['name' => $channelName],
                        ['num' => $channelNum, 'type' => $channelType]
                    );
                }

            }
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            \Log::error($e->getMessage());
            return null;
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return null;
        }

        $channelObjs = Channel::all();
        foreach($channelObjs as $channelObj) {
            $hdpChannelObj = HdpChannel::where('name', $channelObj->name)->first();
            if($hdpChannelObj) {
                HdpChannel::where('channel_code', $channelObj->code)
                    ->where('name', '!=', $channelObj->name)->update(['channel_code' => null]);
                $this->info($channelObj->name."++++++");
                $hdpChannelObj->channel_code = $channelObj->code;
                $hdpChannelObj->save();
            } else {
                $this->info($channelObj->name."-------");
            }
        }
    }

    function xmlAttribute($object, $attribute)
    {
        if(isset($object[$attribute]))
            return (string) $object[$attribute];
        else
            return null;
    }
}
