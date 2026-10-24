const fs = require('fs');
const path = require('path');

function createWavHeader(dataLength, sampleRate = 44100, numChannels = 1, bitsPerSample = 16) {
  const buffer = Buffer.alloc(44);
  const byteRate = sampleRate * numChannels * (bitsPerSample / 8);
  const blockAlign = numChannels * (bitsPerSample / 8);

  buffer.write('RIFF', 0);
  buffer.writeUInt32LE(36 + dataLength, 4);
  buffer.write('WAVE', 8);
  buffer.write('fmt ', 12);
  buffer.writeUInt32LE(16, 16); // Subchunk1Size
  buffer.writeUInt16LE(1, 20);  // PCM format
  buffer.writeUInt16LE(numChannels, 22);
  buffer.writeUInt32LE(sampleRate, 24);
  buffer.writeUInt32LE(byteRate, 28);
  buffer.writeUInt16LE(blockAlign, 32);
  buffer.writeUInt16LE(bitsPerSample, 34);
  buffer.write('data', 36);
  buffer.writeUInt32LE(dataLength, 40);

  return buffer;
}

// Generate Happy Raccoon Sound (1.000 second duration)
// Raccoon joyful chittering: rapid harmonic chirps with playful frequency sweeps and flutter
function generateHappyRaccoon() {
  const sampleRate = 44100;
  const duration = 1.0; // Exactly 1 second
  const numSamples = Math.floor(sampleRate * duration);
  const samples = new Float32Array(numSamples);

  // 4 rapid happy chitter pulses: [start, end, fStart, fEnd, flutterRate, flutterDepth]
  const pulses = [
    { start: 0.02, end: 0.22, fStart: 1150, fEnd: 1550, vibrato: 28, vibDepth: 70 },
    { start: 0.26, end: 0.46, fStart: 1350, fEnd: 1850, vibrato: 32, vibDepth: 90 },
    { start: 0.50, end: 0.72, fStart: 1450, fEnd: 1980, vibrato: 35, vibDepth: 110 },
    { start: 0.76, end: 0.98, fStart: 1300, fEnd: 1700, vibrato: 26, vibDepth: 60 }
  ];

  let phase = 0;
  for (let i = 0; i < numSamples; i++) {
    const t = i / sampleRate;
    let sampleVal = 0;

    for (const p of pulses) {
      if (t >= p.start && t <= p.end) {
        const pulseT = (t - p.start) / (p.end - p.start); // 0 to 1
        
        // Bell envelope with quick attack and smooth release
        const env = Math.sin(pulseT * Math.PI) ** 1.3;

        // Base frequency with upward sweep + rapid chitter vibrato
        const baseF = p.fStart + (p.fEnd - p.fStart) * Math.sin(pulseT * Math.PI * 0.5);
        const fMod = Math.sin(2 * Math.PI * p.vibrato * (t - p.start)) * p.vibDepth;
        const currentFreq = baseF + fMod;

        // Update phase
        phase += (2 * Math.PI * currentFreq) / sampleRate;

        // Raccoon mammalian vocal cord overtones (fundamental + harmonics + formant filtering)
        const harm1 = Math.sin(phase);
        const harm2 = 0.55 * Math.sin(phase * 2 + 0.3);
        const harm3 = 0.30 * Math.sin(phase * 3 + 0.8);
        const harm4 = 0.15 * Math.sin(phase * 4 + 1.2);

        // Vocal tract throat resonance / formant bump around 2800Hz
        const formantWeight = 1.0 + 0.35 * Math.sin(phase * 2.2);

        // Subtle breathy throat flutter
        const breath = (Math.random() * 2 - 1) * 0.08;

        const vocal = (harm1 + harm2 + harm3 + harm4) * formantWeight + breath;
        sampleVal += vocal * env * 0.42;
      }
    }

    samples[i] = Math.max(-0.95, Math.min(0.95, sampleVal));
  }

  // Convert to 16-bit PCM Buffer
  const pcmBuffer = Buffer.alloc(numSamples * 2);
  for (let i = 0; i < numSamples; i++) {
    const intSample = Math.floor(samples[i] * 32767);
    pcmBuffer.writeInt16LE(intSample, i * 2);
  }

  const header = createWavHeader(pcmBuffer.length, sampleRate, 1, 16);
  return Buffer.concat([header, pcmBuffer]);
}

// Generate Afraid / Startled Raccoon Sound Notification (~0.85s duration)
// Startled high screech with sudden attack, fearful tremble, dropping pitch, and alarmed nervous chitters
function generateAfraidRaccoon() {
  const sampleRate = 44100;
  const duration = 0.85; // Notification length
  const numSamples = Math.floor(sampleRate * duration);
  const samples = new Float32Array(numSamples);

  // Alarmed stages:
  // 1: Sudden startled screech (0.00 - 0.32s)
  // 2: Anxious tremor squeak (0.35 - 0.56s)
  // 3: Alarmed whimper chitter (0.59 - 0.80s)
  const pulses = [
    { start: 0.00, end: 0.32, fStart: 3100, fEnd: 2100, vibrato: 42, vibDepth: 180, attackSharp: true },
    { start: 0.35, end: 0.56, fStart: 2400, fEnd: 1750, vibrato: 48, vibDepth: 140, attackSharp: false },
    { start: 0.59, end: 0.80, fStart: 1950, fEnd: 1450, vibrato: 52, vibDepth: 110, attackSharp: false }
  ];

  let phase = 0;
  for (let i = 0; i < numSamples; i++) {
    const t = i / sampleRate;
    let sampleVal = 0;

    for (const p of pulses) {
      if (t >= p.start && t <= p.end) {
        const pulseT = (t - p.start) / (p.end - p.start);
        
        let env;
        if (p.attackSharp) {
          // Explosive startled attack (immediate jump to 100% within 15ms)
          env = pulseT < 0.05 
            ? Math.sin((pulseT / 0.05) * (Math.PI / 2)) 
            : Math.exp(-(pulseT - 0.05) * 4.2);
        } else {
          env = (Math.sin(pulseT * Math.PI) ** 1.4);
        }

        // Descending pitch with fast nervous shiver
        const baseF = p.fStart + (p.fEnd - p.fStart) * (pulseT ** 0.8);
        const fMod = Math.sin(2 * Math.PI * p.vibrato * (t - p.start)) * p.vibDepth;
        const currentFreq = baseF + fMod;

        phase += (2 * Math.PI * currentFreq) / sampleRate;

        // Startled / fearful texture: tenser upper harmonics and anxious breath
        const harm1 = Math.sin(phase);
        const harm2 = 0.65 * Math.sin(phase * 2 + 0.5);
        const harm3 = 0.45 * Math.sin(phase * 3 + 1.1);
        const harm4 = 0.25 * Math.sin(phase * 4 + 1.8);

        // Agitated rasp/noise burst during initial startle
        const panicNoise = (Math.random() * 2 - 1) * (p.attackSharp ? 0.22 : 0.09);

        const vocal = (harm1 + harm2 + harm3 + harm4) * 0.9 + panicNoise;
        sampleVal += vocal * env * 0.44;
      }
    }

    samples[i] = Math.max(-0.95, Math.min(0.95, sampleVal));
  }

  // Convert to 16-bit PCM Buffer
  const pcmBuffer = Buffer.alloc(numSamples * 2);
  for (let i = 0; i < numSamples; i++) {
    const intSample = Math.floor(samples[i] * 32767);
    pcmBuffer.writeInt16LE(intSample, i * 2);
  }

  const header = createWavHeader(pcmBuffer.length, sampleRate, 1, 16);
  return Buffer.concat([header, pcmBuffer]);
}

function main() {
  const soundsDir = path.join(__dirname, '../public/sounds');
  if (!fs.existsSync(soundsDir)) {
    fs.mkdirSync(soundsDir, { recursive: true });
  }

  const happyWav = generateHappyRaccoon();
  const happyPath = path.join(soundsDir, 'happy-raccoon.wav');
  fs.writeFileSync(happyPath, happyWav);
  console.log(`Generated happy-raccoon.wav (${happyWav.length} bytes, 1.00s)`);

  const afraidWav = generateAfraidRaccoon();
  const afraidPath = path.join(soundsDir, 'afraid-raccoon.wav');
  fs.writeFileSync(afraidPath, afraidWav);
  console.log(`Generated afraid-raccoon.wav (${afraidWav.length} bytes, 0.85s)`);
}

main();
