# Repository guidance

## Booking project journal

When working on the surgeon appointment-booking project, update `docs/booking/BOOKING-PROJECT-JOURNAL.fa.md` in the same change at the end of each stage or meaningful decision, test result, blocker, or scope change. Follow its maintenance procedure: preserve history, identify incomplete or simulated work, link verifiable evidence, and recalculate the approximate weighted progress with a brief rationale. Do not record patient information, credentials, or other private data. The journal's percentage is an estimate, not a release-readiness claim. Stage 3 was formally closed. The user subsequently authorized merging and releasing the booking candidate and personally testing it on the live site with a backup, accepting the three known form debts for that initial test. Record this as a limited risk acceptance, not a technical fix. The user now authorizes read-only access to the live site when needed to understand its behavior. Do not install on or modify the live site yourself; the user performs updates and activation. Public unauthenticated observations do not verify authenticated behavior. Do not claim that OAuth, ACF, schema compatibility, backup restoration, or ongoing production safety are verified.

## Child-theme releases

The site owner requests a GitHub Release for every child-theme version increment. Once the versioned ZIP is built and verified, publish the matching `child-vX.Y.Z` release from a commit on the session branch, with a link to the exact ZIP at that tag; attempt to attach the installable ZIP and clearly report any upload failure. Never imply that a release or push installs it on the live site. Do not merge a PR without specific permission.
