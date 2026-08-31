'use strict';

const crypto = require('node:crypto');

/**
 * Admin session handling.
 *
 * One shared admin password (ADMIN_PASSWORD) and a signed, HttpOnly cookie --
 * no user table, no password reset flow, no session store to keep alive. That
 * is the right size for a foundation whose staff is a handful of people, and
 * it means the app has no auth dependency to keep patched.
 */

const COOKIE = 'leo_admin';
const MAX_AGE_SECONDS = 60 * 60 * 12;

function secret() {
  const value = process.env.SESSION_SECRET || process.env.ADMIN_PASSWORD;
  if (!value) {
    throw new Error('SESSION_SECRET or ADMIN_PASSWORD must be set to sign admin sessions');
  }
  return value;
}

function sign(payload) {
  return crypto.createHmac('sha256', secret()).update(payload).digest('base64url');
}

function issue(now = Date.now()) {
  const expires = now + MAX_AGE_SECONDS * 1000;
  const payload = String(expires);
  return `${payload}.${sign(payload)}`;
}

function verify(token, now = Date.now()) {
  if (typeof token !== 'string') return false;
  const [payload, signature] = token.split('.');
  if (!payload || !signature) return false;
  const expected = sign(payload);
  const a = Buffer.from(signature);
  const b = Buffer.from(expected);
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) return false;
  return Number(payload) > now;
}

/** Constant-time password check, so a wrong guess leaks nothing by timing. */
function passwordMatches(candidate) {
  const expected = process.env.ADMIN_PASSWORD || '';
  if (!expected) return false;
  const a = crypto.createHash('sha256').update(String(candidate ?? '')).digest();
  const b = crypto.createHash('sha256').update(expected).digest();
  return crypto.timingSafeEqual(a, b);
}

function parseCookies(header) {
  const out = {};
  for (const part of String(header || '').split(';')) {
    const index = part.indexOf('=');
    if (index === -1) continue;
    out[part.slice(0, index).trim()] = decodeURIComponent(part.slice(index + 1).trim());
  }
  return out;
}

/** Populates `req.isAdmin` for every request; never blocks one. */
function session(req, res, next) {
  const cookies = parseCookies(req.headers.cookie);
  req.isAdmin = Boolean(process.env.ADMIN_PASSWORD) && verify(cookies[COOKIE]);
  next();
}

function login(res) {
  res.cookie(COOKIE, issue(), {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    maxAge: MAX_AGE_SECONDS * 1000,
  });
}

function logout(res) {
  res.clearCookie(COOKIE);
}

/** Route guard for everything under /admin except the login page itself. */
function requireAdmin(req, res, next) {
  if (req.isAdmin) return next();
  const next_ = encodeURIComponent(req.originalUrl);
  res.redirect(`/admin/login?next=${next_}`);
}

module.exports = { COOKIE, session, login, logout, requireAdmin, passwordMatches, issue, verify };
