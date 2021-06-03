<?php

use App\AwsSetting;
use Illuminate\Database\Seeder;

class AwsSettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // moved to /etc/rc.local file
        //cd /home/\$username/
        //su - \$username -c 'cd ~/data-streamer && git pull && cp .env.example .env && yarn && yarn build && pm2 start --name "data-streamer" yarn -- start'

        $API_URL = config('script_instance.api_url');
        $SOCKET_SERVER_HOST = config('script_instance.socket_url');

        $script =
        <<<HERESHELL
            # Note! When APP_ENV=local, the startupScript will automatically re-write these params by actual host data

            su - \$USER_NAME -c 'cd ~/data-streamer && echo "SOCKET_SERVER_HOST={$SOCKET_SERVER_HOST}" >> ./.env'
            su - \$USER_NAME -c 'cd ~/data-streamer && echo "API_URL={$API_URL}" >> ./.env'
        HERESHELL;

        AwsSetting::create([
            'image_id'  => config('aws.image_id', ''),
            'type'      => config('aws.instance_type', ''),
            'storage'   => config('aws.volume_size', 0),
            'script'    => $script,
            'default'   => true
        ]);
    }
}
