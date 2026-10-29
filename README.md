# LearnDash Jailbreak

A self-maintained build of **LearnDash LMS 5.2.2** for WordPress, with the licence checks, update checks, telemetry and remote-support hooks switched off, and the AI features opened up to more providers than just OpenAI.

LearnDash is licensed under the GNU GPL v2 (or later), like WordPress itself. The GPL gives everyone who receives the code the right to study it, change it and share it. This repository uses that right. "Jailbreak" is a joke: nothing here is cracked or pirated. It's GPL code, modified and shared as the licence allows.

> **Not affiliated with LearnDash, StellarWP or Liquid Web.** "LearnDash" is their trademark and is used here only to say what this code is a modified version of. There is no support from them, and no warranty from me (see [LICENSE](sfwd-lms/LICENSE)).

## Why this exists

After LearnDash was acquired, its pricing and licensing changed a lot. This build keeps an existing LearnDash site running without depending on the vendor's licensing servers, and stops the plugin sending data about your site to third parties.

## What's changed

The full write-up, file by file, is in [`sfwd-lms/SELF-HOSTED-CHANGES.md`](sfwd-lms/SELF-HOSTED-CHANGES.md). In short:

- **Licence checks bypassed.** All three licensing systems (the Hub, the newer Status Checker, and StellarWP's Harbor) report the licence as valid without contacting anyone.
- **Updates off.** The dashboard will never offer a LearnDash update, so it can't overwrite these changes.
- **No phoning home.** Telemetry, the daily licence "verify" call, remote ad banners and the Harbor catalog fetch are all disabled.
- **TrustedLogin removed.** The vendor remote-support access page is never registered.
- **Multi-provider AI.** The Virtual Instructor, Course Outline and Quiz Creation tools can use Anthropic (Claude), Google Gemini, OpenAI, or any self-hosted OpenAI-compatible server (Ollama, LM Studio, vLLM and similar). Set this up under LearnDash → Advanced Settings → AI Integrations.

Course, lesson, quiz, student-facing and payment code is unchanged.

## Installing

1. On this page, click the green **Code** button → **Download ZIP**, and unzip it.
2. Inside, find the **`sfwd-lms`** folder. Zip **just that folder** (so the zip contains `sfwd-lms/` at the top level).
3. In WordPress: **Plugins → Add New → Upload Plugin**, choose your zip, and install. If you're replacing an existing LearnDash, WordPress will offer to replace the current version.

The folder must stay named `sfwd-lms`, which is what LearnDash and its add-ons expect.

**Back up your site before installing or replacing any plugin.**

## ⚠️ Security updates are now your job

Because updates are switched off, **security fixes will not arrive automatically.** Keep an eye on WPScan or Wordfence for vulnerabilities in "LearnDash LMS". When a fix comes out, get the new release and re-apply the changes listed in `SELF-HOSTED-CHANGES.md`. That file lists every location that needs to be changed.

## Layout

- `sfwd-lms/`: the plugin itself, ready to zip and install
- `sfwd-lms/SELF-HOSTED-CHANGES.md`: every change, why it was made, and how to carry it forward to a new version
- `sfwd-lms/GEMINI-REVIEW.md`: notes from a code review of the changes

## Licence

GPL v2 or later, the same as upstream LearnDash. See [`sfwd-lms/LICENSE`](sfwd-lms/LICENSE). If you share a modified version, the GPL requires you to share it under the same licence.
