<?php

    $openai_key = getenv('OPENAI_KEY');
    $response = null;
    $text_article = null;
    $open_ai = new OpenAi($openai_key);
    //mime type video/webm

    function createImage($prompt)
    {
        $leap_bearer = getenv('LEAP_BEARER');
        $curl = curl_init();

        $data = array(
            'prompt'         => "$prompt, 16K resolution, 8k resolution, DeviantArt, 4k detailed post processing, atmospheric, hyper realistic, 8k, epic composition, cinematic",
            'negativePrompt' => 'asymmetric, watermarks',
            'steps'          => 50,
            'width'          => 512,
            'height'         => 512,
            'numberOfImages' => 1,
            'promptStrength' => 7,
            'seed'           => mt_rand(1000000, 9999999),
            'restoreFaces'   => true,
        );
        $model = 'd66b1686-5e5d-43b2-a2e7-d295d679917c';
        curl_setopt_array($curl, array(
            CURLOPT_URL            => "https://api.leapml.dev/api/v1/images/models/$model/inferences",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array(
                'accept: application/json',
                "authorization: Bearer $leap_bearer",
                'content-type: application/json',
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $photo_id = json_decode($response, true)['id'];
        $image_url = '';

        while (true)
        {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL            => "https://api.leapml.dev/api/v1/images/models/$model/inferences/$photo_id",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPHEADER     => array(
                    'accept: application/json',
                    "authorization: Bearer $leap_bearer",
                    'content-type: application/json',
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            $response = json_decode($response, true);
            $state = $response['state'];
            if ($state === 'finished')
            {
                $image_url = $response['images'][0]['uri'];
                break;
            }
            sleep(5);
        }

        return $image_url;
    }
    // Check if image file is a actual image or fake image
    if (isset($_POST['submit']))
    {
        /*
        Replace the following code with the following code to use OpenAI's GPT-3.5-Turbo model. Could do gpt-4 as well now that I have access.
                $complete = json_decode($open_ai->completion([
                    'model' => 'gpt-3.5-turbo',//'text-davinci-003',
                    'prompt' => 'Write me an article about the following subject: ' . $_POST['makeItAbout'],
                    'temperature' => 0.6,
                    'max_tokens' => 1250,
                ]), true);
            */
        $complete = json_decode($open_ai->chat(array(
            'model'    => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role'    => 'system',
                    'content' => 'You are a storyteller that loves to write articles about subjects. Create an article about whatever I send and make sure that it is interesting.',
                ),
                array(
                    'role'    => 'user',
                    'content' => $_POST['makeItAbout'],
                ),
            ),
            'temperature'       => 1.0,
            'max_tokens'        => 4000,
            'frequency_penalty' => 0,
            'presence_penalty'  => 0,
        )), true);
        //$text_article = $complete['choices'][0]['text']; //This way was for completions and not chat
        $text_article = str_replace('"', '', $complete['choices'][0]['message']['content']);
    }
    elseif (isset($_POST['approve']))
    {
        $url = 'https://api.elevenlabs.io/v1/text-to-speech/ErXwobaYiN019PkySvjV';
        $voice_ai = 'd69ccf9818e8e8857c6a63ae819e72b5';
        $voice_id = 'ErXwobaYiN019PkySvjV'; //Antoni
        $apiKey = getenv('XI_API_KEY');
        //replace double quotes with single quotes from $_POST['text_article']
        $article = $_POST['text_article'].' Thanks for watching, and remember to follow for more content like this!';
        $data = json_encode(array('text' => $article));
        $title = strtoupper($_POST['videoTitle']);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: audio/mpeg',
            "xi-api-key: $apiKey",
            'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);

        curl_close($ch);

        $stmt = $pdo->prepare('INSERT INTO videos (created) VALUES (NOW())');
        $stmt->execute();
        $id = $pdo->lastInsertId();
        $audio_path = $id.'.mp3';
        file_put_contents($audio_path, $response);

        //Get the duration of the audio file
        $getID3 = new getID3();
        $file = $getID3->analyze($audio_path);
        $duration = floor($file['playtime_seconds']);
        //One image every 5 seconds
        $image_count = 1;
        $remaining_duration = $duration - 5;
        if ($remaining_duration >= 5)
        {
            $image_count += ceil($remaining_duration / 5);
        }
        //round down

        $photo_ids = array();
        $photo_paths = array();

        foreach (range(1, $image_count) as $i)
        {
            /*
            Replace dall-e image gen with leapml image gen (stable diffusion api)
            $complete = $open_ai->image([
                "prompt" => $_POST['imagesAbout'] . ". Photography. Realistic. Photorealism.",
                "n" => 1,
                "size" => "512x512",
                "response_format" => "url",
            ]);
            $complete = json_decode($complete, true);
            */
            $image_url = createImage($_POST['imagesAbout']);
            $image = file_get_contents($image_url);
            $image_file = $id.'_'.$i.'.png';
            $photo_paths[] = $image_file;
            file_put_contents($image_file, $image);
            $stmt = $pdo->prepare('INSERT INTO photos (video_id, url) VALUES (:video_id, :url)');
            $stmt->execute(array(
                'video_id' => $id,
                'url'      => $image_url,
            ));
            $photo_ids[] = $pdo->lastInsertId();
        }

        $images = $photo_paths;
        $audio = $audio_path;

        $images_list = '';
        $complex_filter = '';
        $concat_string = '';

        foreach ($images as $key => $image)
        {
            $images_list .= '-t 5 -i '.escapeshellarg($image).' ';
            if ($key == 0)
            {
                $complex_filter .= "[0:v]zoompan=z='if(lte(zoom,1.0),1.2,max(1.001,zoom-0.0015))':d=125,fade=t=out:st=4:d=1,drawtext=text='$title': fontcolor=white: fontsize=72: box=1: boxcolor=black@0.9: boxborderw=9: x=(w-text_w)/2: y=(h-text_h)/3[v0]; ";
            }
            elseif ($key == count($images) - 1)
            {
                $complex_filter .= "[$key:v]zoompan=z='if(lte(zoom,1.0),1.2,max(1.001,zoom-0.0015))':d=125,fade=t=out:st=4:d=1,drawtext=text='Follow for more!': fontcolor=white: fontsize=72: box=1: boxcolor=black@0.9: boxborderw=9: x=(w-text_w)/2: y=(h-text_h)/3[v$key];";
            }
            else
            {
                $complex_filter .= "[$key:v]zoompan=z='if(lte(zoom,1.0),1.2,max(1.001,zoom-0.0015))':d=125,fade=t=in:st=0:d=1,fade=t=out:st=4:d=1[v$key]; ";
            }
            $concat_string .= "[v$key]";
        }
        $concat_string .= "concat=n=$image_count:v=1:a=0,format=yuv420p[v]";
        $complex_filter .= $concat_string;

        $video_name = 'video-'.$id;
        //$cmd = "ffmpeg ".$images_list."-i ".escapeshellarg($audio)." -t ".escapeshellarg($video_duration)." -filter_complex \"[0:v]crop=iw/2:ih:0:0,setpts=2.5*PTS[left]; [0:v]crop=iw/2:ih:iw/2:0,setpts=2.5*PTS[right]; [left][right]hstack[v]; [1:a]afade=t=out:st=28.75:d=1.25[a]\" -map \"[v]\" -map \"[a]\" -c:v libx264 -c:a aac -y " . escapeshellarg($video_name . ".mp4");
        //$cmd = "ffmpeg -r 1/5 -start_number 1 -i ". $id . "_%d.png -i ".escapeshellarg($audio)." -c:v libx264 -vf \"fps=25,format=yuv420p\" " . escapeshellarg($video_name . ".mp4") . " 2>&1";
        //ffmpeg -r 1/5 -start_number 1 -i 11-%d.png -i 11.mp3 -c:v libx264 -vf "fps=25,format=yuv420p" output.mp4
        $cmd = "ffmpeg $images_list -i ".escapeshellarg($audio)." -filter_complex \"$complex_filter\" -map \"[v]\" -map $image_count:a -s \"512x512\" -c:v libx264 -y ".escapeshellarg($video_name.'.mp4').' 2>&1';
        $vid_tag_path = $video_name.'.mp4';

        exec($cmd);
        //-vf "zoompan=z='if(lte(zoom,1.0),1.5,max(1.001,zoom-0.0015))':d=20"
        //ffmpeg -r 1/5 -start_number 1 -i 11-%d.png -i 11.mp3 -c:v libx264 -vf "fps=25,format=yuv420p" output.mp4
    }
