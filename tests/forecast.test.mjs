import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

function php(code, input = {}) {
  const result = spawnSync('php', ['-r', `require 'lib/forecast/service.php'; $input=json_decode($argv[1],true); ${code}`, JSON.stringify(input)], {encoding:'utf8'});
  assert.equal(result.status, 0, result.stderr);
  assert.equal(result.stderr, '');
  return JSON.parse(result.stdout);
}

test('a busy refresh serves labelled stale data only up to six hours, with no network request', () => {
  const directory = mkdtempSync(join(tmpdir(),'roof-forecast-cache-test-'));
  try {
    for (const [age, expectedRows] of [[90*60,1],[7*3600,0]]) {
      const cache = join(directory,'forecast.json');
      writeFileSync(cache,JSON.stringify({fetched_at:Math.floor(Date.now()/1000)-age,rows:[{utcTime:'2026-09-07T22:00:00'}]}));
      const result=php('$lock=fopen($input["cache"].".lock","c"); flock($lock,LOCK_EX); $result=forecast_fetch_rows($input["cache"],time()); flock($lock,LOCK_UN); fclose($lock); echo json_encode($result);',{cache});
      assert.equal(result.stale,true);
      assert.equal(result.rows.length,expectedRows);
    }
  } finally { rmSync(directory,{recursive:true,force:true}); }
});

test('all cloud heights participate, limits are inclusive, seeing is optional, missing data stays unknown', () => {
  const row = {lowcloud:0,medcloud:0,highcloud:0,seeingIndex:0};
  const classify = (row, rules = {}) => php('echo json_encode(astro_classify_forecast($input["row"], array_replace(forecast_defaults(),$input["rules"])));', {row,rules}).state;
  assert.equal(classify(row), 'good');
  assert.equal(classify({...row,highcloud:1}), 'fair');
  assert.equal(classify({...row,medcloud:40}), 'fair');
  assert.equal(classify({...row,lowcloud:41}), 'poor');
  assert.equal(classify({...row,highcloud:null}), 'unknown');
  assert.equal(classify({...row,lowcloud:'bad'}), 'unknown');
  assert.equal(classify(row,{useSeeing:true}), 'poor');
  assert.equal(classify({...row,seeingIndex:5.5},{useSeeing:true}), 'good');
  assert.equal(classify({...row,seeingIndex:4},{useSeeing:true}), 'fair');
  assert.equal(classify({...row,seeingIndex:null},{useSeeing:true}), 'unknown');
  assert.equal(classify({...row,highcloud:10},{greenCloud:10}), 'good');
});

test('night stays anchored before sunrise, changes at sunrise, and spans both calendar dates across DST', () => {
  for (const date of ['2026-09-07','2026-03-29','2026-10-25']) {
    const result = php('$date=$input["date"]; $sunrise=astro_sun_event($date,"sunrise"); echo json_encode([forecast_night_date($sunrise-1),forecast_night_date($sunrise),astro_build_night_plan(date("Y-m-d",strtotime($date." -1 day")),[],forecast_defaults())]);', {date});
    assert.equal(result[1], date);
    assert.notEqual(result[0], date);
    assert.equal(result[2].date, result[0]);
    assert.ok(result[2].end > result[2].start);
    assert.ok(result[2].end-result[2].start < 18*3600);
    assert.equal(result[2].best_window_start, null);
    for (const track of ['darkness','moon']) {
      assert.equal(result[2][track][0].start,result[2].start);
      assert.equal(result[2][track].at(-1).end,result[2].end);
      result[2][track].slice(1).forEach((segment,i)=>assert.equal(segment.start,result[2][track][i].end));
    }
  }
});

test('sunset-to-sunrise clips hourly forecasts; missing hours cannot become an ideal window', () => {
  const result = php('$start=strtotime("2026-09-07 20:30 UTC"); $rows=[]; foreach([0,1,4] as $hour) $rows[]=["utcTime"=>gmdate("Y-m-d\\TH:i:s",$start-1800+$hour*3600),"lowcloud"=>0,"medcloud"=>0,"highcloud"=>0,"dayOrNight"=>"D"]; $sky=astro_forecast_segments($rows,$start,$start+5*3600,forecast_defaults()); echo json_encode([$sky,astro_best_window($sky,[["start"=>$start,"end"=>$start+5*3600,"state"=>"dark"]],[["start"=>$start,"end"=>$start+5*3600,"state"=>"down"]])]);');
  assert.equal(result[0][0].end-result[0][0].start,1800);
  assert.ok(result[0][1].end < result[0][2].start);
  assert.equal(result[1].end-result[1].start,5400);
});

test('forecast settings persist, invalid rules are rejected without changing saved configuration', () => {
  const directory = mkdtempSync(join(tmpdir(),'roof-forecast-test-'));
  const env = {...process.env,ROOF_CONFIG_DB_PATH:join(directory,'config.db'),REDIRECT_STATUS:'1'};
  const request = (script,body) => {
    const input=body?JSON.stringify(body):'';
    const result=spawnSync('php-cgi',[],{input,encoding:'utf8',env:{...env,SCRIPT_FILENAME:resolve(script),REQUEST_METHOD:body?'POST':'GET',CONTENT_TYPE:'application/json',CONTENT_LENGTH:String(Buffer.byteLength(input))}});
    assert.equal(result.status,0,result.stderr);
    const [headers,...parts]=result.stdout.split(/\r?\n\r?\n/);
    return {headers,body:JSON.parse(parts.join('\n\n'))};
  };
  try {
    const defaults=request('get_config.php').body.forecast;
    assert.equal(defaults.greenCloud,0);
    const rules={...defaults,greenCloud:5,amberCloud:30,useSeeing:true};
    assert.equal(request('save_config.php',{forecast:rules}).body.status,'ok');
    assert.deepEqual(request('get_config.php').body.forecast,rules);
    for (const change of [{greenCloud:-1},{amberCloud:101},{amberCloud:4},{greenSeeing:2},{useSeeing:'true'},{greenCloud:null},{greenSeeing:11}]) {
      assert.match(request('save_config.php',{forecast:{...rules,...change}}).headers,/422/);
    }
    assert.deepEqual(request('get_config.php').body.forecast,rules);
    const cache=join(directory,'forecast.json');
    writeFileSync(cache,JSON.stringify({fetched_at:Math.floor(Date.now()/1000),rows:[]}));
    env.ROOF_FORECAST_CACHE_PATH=cache;
    const response=request('forecast.php').body;
    assert.equal(response.plan.available,true);
    assert.equal(response.plan.best_window_start,null);
    assert.equal(response.plan.coverage_seconds,0);
  } finally { rmSync(directory,{recursive:true,force:true}); }
});
