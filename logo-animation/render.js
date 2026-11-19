// Usage: node render.js <theme dark|light> <out file> [transparent=0|1] [fps=60] [format=horizontal|vertical]
// Renders index.html frame by frame (deterministic) and encodes with ffmpeg.
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const path = require('path');
(async () => {
  const [theme = 'dark', out = `angels-${theme}.mp4`, transp = '0', fpsArg = '60', format = 'horizontal'] = process.argv.slice(2);
  const fps = +fpsArg, alpha = transp === '1', vert = format === 'vertical';
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: vert ? { width: 1080, height: 1920 } : { width: 1920, height: 1080 }, deviceScaleFactor: 1 });
  const url = 'file://' + path.resolve(__dirname, 'index.html') + `?capture=1&theme=${theme}${alpha ? '&bg=0' : ''}${vert ? '&format=vertical' : ''}`;
  await p.goto(url);
  const dur = await p.evaluate(() => window.DURATION);
  const n = Math.round(dur * fps);
  const args = ['-y', '-f', 'image2pipe', '-framerate', String(fps), '-i', '-'];
  if (alpha && out.endsWith('.webm')) args.push('-c:v', 'libvpx-vp9', '-pix_fmt', 'yuva420p', '-b:v', '0', '-crf', '24', '-row-mt', '1', out);
  else if (alpha) args.push('-c:v', 'prores_ks', '-profile:v', '4444', '-qscale:v', '9', '-alpha_bits', '8', '-pix_fmt', 'yuva444p10le', '-vendor', 'apl0', out);
  else args.push('-c:v', 'libx264', '-preset', 'slow', '-crf', '14', '-pix_fmt', 'yuv420p', '-profile:v', 'high', '-movflags', '+faststart', out);
  const ff = spawn('ffmpeg', args, { stdio: ['pipe', 'ignore', 'inherit'] });
  for (let i = 0; i < n; i++) {
    await p.evaluate(t => renderAt(t), i / fps);
    const buf = await p.screenshot({ type: 'png', omitBackground: alpha });
    if (!ff.stdin.write(buf)) await new Promise(r => ff.stdin.once('drain', r));
  }
  ff.stdin.end();
  await new Promise(r => ff.on('close', r));
  await b.close();
  console.log('done', out, n, 'frames');
})();
