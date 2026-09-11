<?php
declare(strict_types=1);
require __DIR__ . '/../api/contact-lib.php';
umask(0077);
// Separate CLI workers exercise OS flock; synthetic callback never contacts SMTP.
if (($argv[1] ?? '') === 'worker') {
    $state = new \Nurlift\Contact\State($argv[2], str_repeat('s',64));
    try {
        if ($argv[3] === 'rate') { $state->attempt('192.0.2.10',time()); echo '200'; }
        else {
            $data=\Nurlift\Contact\payload(json_encode(['name'=>'Test Person','email'=>'test@example.com','phone'=>'+1 212 555 1234','subject'=>'Would like information','source'=>'Nurlift EN','page_language'=>'en','page'=>'/en/','idempotency_key'=>str_repeat('c',32),'website'=>'']));
            $state->deliver($data,time(),function()use($argv){file_put_contents($argv[2].'/sent','sent\n',FILE_APPEND|LOCK_EX);usleep(200000);});echo '200';
        }
    } catch (\Nurlift\Contact\Rejected $e) { echo $e->status; }
    exit;
}
$root=sys_get_temp_dir().'/nurlift-concurrent-'.bin2hex(random_bytes(8));mkdir($root,0700);
new \Nurlift\Contact\State($root,str_repeat('s',64));
try {
    foreach(['rate','delivery'] as $mode){
        $workers=[];
        for($i=0;$i<10;$i++){
            $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$root,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process))throw new RuntimeException('worker unavailable');
            fclose($pipes[0]);$workers[]=[$process,$pipes];
        }
        $results=[];
        foreach($workers as [$p,$pipes]){$results[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0||$err!=='')throw new RuntimeException('worker failed');}
        if($mode==='rate'){
            if(count(array_filter($results,fn($r)=>$r==='200'))!==5||count(array_filter($results,fn($r)=>$r==='429'))!==5)throw new RuntimeException('rate race');
        }else{
            if(file_get_contents($root.'/sent')!=='sent\n'||!in_array('200',$results,true)||array_diff($results,['200','503']))throw new RuntimeException('delivery race');
        }
        echo 'PASS concurrent '.$mode.' (10 workers)' . PHP_EOL;
    }
}finally{
    foreach(['rate-limit','idempotency','logs'] as $dir){foreach(glob($root.'/'.$dir.'/*')?:[] as $f)unlink($f);rmdir($root.'/'.$dir);}
    foreach(['.state.lock','sent'] as $f)if(is_file($root.'/'.$f))unlink($root.'/'.$f);rmdir($root);
}
