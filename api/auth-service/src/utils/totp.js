const crypto = require("crypto");

const BASE32_ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

const base32Encode = (buffer) => {
  let bits = 0;
  let value = 0;
  let output = "";

  for (const byte of buffer) {
    value = (value << 8) | byte;
    bits += 8;

    while (bits >= 5) {
      output += BASE32_ALPHABET[(value >>> (bits - 5)) & 31];
      bits -= 5;
    }
  }

  if (bits > 0) {
    output += BASE32_ALPHABET[(value << (5 - bits)) & 31];
  }

  return output;
};

const base32Decode = (input) => {
  const cleaned = input.replace(/=+$/, "").toUpperCase();
  let bits = 0;
  let value = 0;
  const output = [];

  for (const char of cleaned) {
    const index = BASE32_ALPHABET.indexOf(char);

    if (index === -1) {
      continue;
    }

    value = (value << 5) | index;
    bits += 5;

    if (bits >= 8) {
      output.push((value >>> (bits - 8)) & 255);
      bits -= 8;
    }
  }

  return Buffer.from(output);
};

const generateSecret = (length = 20) => base32Encode(crypto.randomBytes(length));

const generateCode = (secret, counter) => {
  const key = base32Decode(secret);
  const buffer = Buffer.alloc(8);
  buffer.writeBigUInt64BE(BigInt(counter));

  const hmac = crypto.createHmac("sha1", key).update(buffer).digest();
  const offset = hmac[hmac.length - 1] & 0x0f;
  const binary =
    ((hmac[offset] & 0x7f) << 24) |
    ((hmac[offset + 1] & 0xff) << 16) |
    ((hmac[offset + 2] & 0xff) << 8) |
    (hmac[offset + 3] & 0xff);

  return String(binary % 1000000).padStart(6, "0");
};

const verifyCode = (secret, code, window = 1) => {
  if (!secret || !code) {
    return false;
  }

  const sanitized = String(code).replace(/\s/g, "");

  if (!/^\d{6}$/.test(sanitized)) {
    return false;
  }

  const counter = Math.floor(Date.now() / 1000 / 30);

  for (let i = -window; i <= window; i += 1) {
    if (generateCode(secret, counter + i) === sanitized) {
      return true;
    }
  }

  return false;
};

const buildOtpAuthUrl = ({ secret, email, issuer = "VIVAVTU" }) => {
  const label = encodeURIComponent(`${issuer}:${email}`);
  const params = new URLSearchParams({
    secret,
    issuer,
    algorithm: "SHA1",
    digits: "6",
    period: "30",
  });

  return `otpauth://totp/${label}?${params.toString()}`;
};

const generateRecoveryCodes = (count = 8) => {
  const codes = [];

  for (let i = 0; i < count; i += 1) {
    codes.push(crypto.randomBytes(5).toString("hex").toUpperCase());
  }

  return codes;
};

const hashRecoveryCode = (code) =>
  crypto.createHash("sha256").update(String(code).trim().toUpperCase()).digest("hex");

module.exports = {
  generateSecret,
  generateCode,
  verifyCode,
  buildOtpAuthUrl,
  generateRecoveryCodes,
  hashRecoveryCode,
  base32Encode,
  base32Decode,
};
