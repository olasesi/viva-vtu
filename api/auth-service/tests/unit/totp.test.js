const crypto = require("crypto");
const {
  generateSecret,
  generateCode,
  verifyCode,
  buildOtpAuthUrl,
  generateRecoveryCodes,
  hashRecoveryCode,
  base32Encode,
  base32Decode,
} = require("../../src/utils/totp");

describe("TOTP utilities", () => {
  it("generates a base32 secret that round-trips through the decoder", () => {
    const secret = generateSecret();
    expect(secret).toMatch(/^[A-Z2-7]+$/);

    const decoded = base32Decode(secret);
    expect(base32Encode(decoded)).toBe(secret);
  });

  it("generates a six digit code", () => {
    const secret = generateSecret();
    expect(generateCode(secret, 1)).toMatch(/^\d{6}$/);
  });

  it("verifies the current code within the allowed window", () => {
    const secret = generateSecret();
    const counter = Math.floor(Date.now() / 1000 / 30);
    const code = generateCode(secret, counter);

    expect(verifyCode(secret, code)).toBe(true);
    expect(verifyCode(secret, "000000")).toBe(false);
    expect(verifyCode(secret, "not-a-code")).toBe(false);
    expect(verifyCode(secret, "")).toBe(false);
  });

  it("matches the RFC 6238 reference vector", () => {
    const secret = base32Encode(Buffer.from("12345678901234567890"));
    expect(generateCode(secret, Math.floor(59 / 30))).toBe("287082");
  });

  it("builds a valid otpauth url", () => {
    const url = buildOtpAuthUrl({
      secret: "JBSWY3DPEHPK3PXP",
      email: "user@example.com",
      issuer: "VIVAVTU",
    });

    expect(url).toContain("otpauth://totp/");
    expect(url).toContain("secret=JBSWY3DPEHPK3PXP");
    expect(url).toContain("issuer=VIVAVTU");
  });

  it("generates unique recovery codes and hashes them deterministically", () => {
    const codes = generateRecoveryCodes();
    expect(codes).toHaveLength(8);
    expect(new Set(codes).size).toBe(8);

    const hash = hashRecoveryCode(codes[0]);
    expect(hash).toMatch(/^[a-f0-9]{64}$/);
    expect(hashRecoveryCode(codes[0].toLowerCase())).toBe(hash);
    expect(hash).not.toBe(crypto.createHash("sha256").update("nope").digest("hex"));
  });
});
