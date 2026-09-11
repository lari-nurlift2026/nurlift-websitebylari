<?php
declare(strict_types=1);
require __DIR__ . '/../api/contact-lib.php';
use Nurlift\Contact\State;
use Nurlift\Contact\Rejected;
use function Nurlift\Contact\{payload, language, requestGate};
umask(0077);
$count = 0;
function check(bool $ok, string $name): void { global $count; if (!$ok) throw new RuntimeException($name); $count++; echo "PASS $name\n"; }
function rejects(callable $fn, int $status, string $name): void {
    try { $fn(); } catch (Rejected $e) { check($e->status === $status, $name); return; }
    throw new RuntimeException('Expected rejection: ' . $name);
}
function fixture(): array { return ['name'=>'João Silva','email'=>'Jane@EXAMPLE.COM','phone'=>'+55 (11) 99999-1234','subject'=>'Quero conhecer a solução','source'=>'Nurlift PT','page_language'=>'pt','page'=>'/','idempotency_key'=>'0123456789abcdefghijklmnop','website'=>'']; }
function valid(array $data): array { return payload(json_encode($data, JSON_THROW_ON_ERROR)); }
$root = sys_get_temp_dir() . '/nurlift-test-' . bin2hex(random_bytes(8)); mkdir($root,0700);
try {
    foreach (['Nurlift PT'=>['pt','/'],'Nurlift EN'=>['en','/en/'],'Dropper PT'=>['pt','/dropper/'],'Dropper EN'=>['en','/en/dropper/']] as $source=>$pair) {
        $d=fixture(); [$d['page_language'],$d['page']]=$pair; $d['source']=$source;
        check(valid($d)['source']===$source, 'valid '.$source);
    }
    check(valid(fixture())['email']==='Jane@example.com','domain normalized, local part preserved');
    check(language('Quero conhecer a solução','en')==='pt','PT on EN');
    check(language('I would like information','pt')==='en','EN on PT');
    check(language('Demo Nurlift Dropper','en')==='en','ambiguous EN fallback');
    check(language('Demo','pt')==='pt','short PT fallback');
    check(language('quero conhecer would like','en')==='en','mixed fallback');
    foreach (['email'=>'invalid','phone'=>'123','name'=>str_repeat('é',101),'subject'=>str_repeat('a',201),'page'=>'/en/','website'=>'bot','idempotency_key'=>'short'] as $field=>$value) {
        $d=fixture();$d[$field]=$value; rejects(fn()=>valid($d),422,'reject '.$field);
    }
    foreach (['name','email','subject','phone'] as $field) {
        $d=fixture();$d[$field].="\r\nBcc: victim@example.com";rejects(fn()=>valid($d),422,'CRLF '.$field);
    }
    $d=fixture();$d['timestamp']='invented'; rejects(fn()=>valid($d),422,'reject extra timestamp');
    $d=fixture();unset($d['name']);rejects(fn()=>valid($d),422,'missing field');
    $d=fixture();$d['name']=[];rejects(fn()=>valid($d),422,'non-string');
    rejects(fn()=>payload(str_repeat(' ',8193)),413,'oversized body');
    rejects(fn()=>payload('{broken'),422,'malformed JSON');
    rejects(fn()=>payload('[]'),422,'array body');
    $server=['REQUEST_METHOD'=>'POST','CONTENT_TYPE'=>'application/json; charset=utf-8','HTTP_ORIGIN'=>'https://nurlift.com','HTTP_SEC_FETCH_SITE'=>'same-origin'];
    requestGate($server,'https://nurlift.com'); check(true,'valid request gate');
    foreach (['REQUEST_METHOD'=>['GET',405],'CONTENT_TYPE'=>['text/plain',415],'HTTP_ORIGIN'=>['https://evil.example',403],'HTTP_SEC_FETCH_SITE'=>['cross-site',403],'CONTENT_LENGTH'=>['8193',413]] as $key=>$case) {
        $s=$server;$s[$key]=$case[0];rejects(fn()=>requestGate($s,'https://nurlift.com'),$case[1],'gate '.$key);
    }
    $s=$server;unset($s['HTTP_ORIGIN']);rejects(fn()=>requestGate($s,'https://nurlift.com'),403,'missing origin');
    $state = new State($root,str_repeat('s',64));$now=time();
    for($i=0;$i<5;$i++)$state->attempt('192.0.2.1',$now);
    rejects(fn()=>$state->attempt('192.0.2.1',$now),429,'sixth attempt');
    $state->attempt('192.0.2.1',$now+601);check(true,'rate expiry');
    $sent=0;$data=valid(fixture());$lead=null;
    $send=function($value)use(&$sent,&$lead){$sent++;$lead=$value;};
    $first=$state->deliver($data,$now,$send);$second=$state->deliver($data,$now+1,$send);
    check($sent===1 && $first===$second,'completed duplicate no email');
    check($lead['timestamp']===gmdate('c',$now) && $lead['source']==='Nurlift PT' && isset($lead['request_id']),'server attribution');
    check(!isset($lead['idempotency_key']) && !isset($lead['website']),'no operational tokens in email');
    $changed=$data;$changed['subject']='Quero conhecer outra solução';rejects(fn()=>$state->deliver($changed,$now,$send),422,'key payload conflict');
    $pending=$data;$pending['idempotency_key']=str_repeat('p',32);
    $state->deliver($pending,$now,function()use($state,$pending,$now,$send){rejects(fn()=>$state->deliver($pending,$now,$send),503,'concurrent pending');});
    $bad=$data;$bad['idempotency_key']=str_repeat('f',32);
    try{$state->deliver($bad,$now,function(){throw new RuntimeException('synthetic transport failure');});}catch(RuntimeException){}
    rejects(fn()=>$state->deliver($bad,$now+1,$send),503,'uncertain failure not retried');
    $state->log($first['request_id'],'accepted',$now);
    $stored='';foreach(['rate-limit','idempotency','logs'] as $dir)foreach(glob($root.'/'.$dir.'/*') as $file)$stored.=file_get_contents($file);
    foreach(['João','Jane@','99999','Quero conhecer','192.0.2.1'] as $personal)check(!str_contains($stored,$personal),'state excludes '.$personal);
    $state->cleanup($now+86401);check(count(glob($root.'/idempotency/*.json'))===0,'idempotency expiry cleanup');
    check(language('SOLUÇÃO ORÇAMENTO', 'en') === 'pt', 'Unicode uppercase detection');
    $edge=fixture();$edge['name']=str_repeat('é',100);$edge['subject']=str_repeat('a',200);check(valid($edge)['name']===$edge['name'],'Unicode exact limits');
    $edge=fixture();$edge['phone']='+1 (212) 555-1234';check(valid($edge)['phone']===$edge['phone'],'international phone');
    require __DIR__ . '/../vendor/autoload.php';
    require __DIR__ . '/../api/contact-mail.php';
    $mail=\Nurlift\Contact\composeMail(['smtp_host'=>'smtp.example.com','smtp_port'=>465,'smtp_username'=>'contact@example.com','smtp_password'=>'synthetic-test-only','mail_from'=>'contact@example.com','mail_to'=>'contact@example.com'], $lead);
    check($mail->From==='contact@example.com' && array_values($mail->getReplyToAddresses())[0][0]==='Jane@example.com','fixed From validated Reply-To');
    check($mail->Subject==='[Nurlift Lead] Nurlift PT','server generated subject');
    check($mail->SMTPSecure==='ssl' && $mail->SMTPAuth && $mail->SMTPDebug===0,'SMTP authentication TLS debug off');
    check($mail->preSend(),'mail can be composed without SMTP');
    check(str_contains($mail->Body,'Server timestamp (UTC):') && str_contains($mail->Body,'Originating page: /'),'plain text attribution fields');
    echo "SUCCESS $count checks\n";
} finally {
    foreach(['rate-limit','idempotency','logs'] as $dir){foreach(glob($root.'/'.$dir.'/*')?:[] as $f)unlink($f);rmdir($root.'/'.$dir);}
    if(is_file($root.'/.state.lock'))unlink($root.'/.state.lock');rmdir($root);
}
