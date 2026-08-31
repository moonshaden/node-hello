# Supplied source artwork

The originals the client handed over, kept unedited. Everything the site ships
is derived from these, so a future change starts from the artwork rather than
from a re-derivation of a derivation.

They are not on the live WordPress site. Its header logo is the flat horizontal
wordmark `New-Logo.png`, and a search of the media library for logo, lion,
crest, emblem and seal turns up nothing like the crest — so these cannot be
re-fetched the way `../data/` was scraped. That is the reason they are committed
here rather than left to be re-supplied.

| File | What it is | What ships from it |
| --- | --- | --- |
| `crest-original.png` | Lion crest over LEO / FOUNDATION, 911x850, no pillars strip | — |
| `crest-pillars.webp` | The same crest plus the LEADERSHIP / EDUCATION / OPPORTUNITY strip, 1254 square, solid white ground | `public/img/brand/leo-crest.png` and `leo-crest-header.png` |
| `crest-pillars-broken-alpha.webp` | The same artwork with a **damaged alpha channel** — ragged transparency eats chunks out of the letterforms and the pillar icon. Kept only so nobody reaches for it again by mistake | nothing |
| `keian-gcu-promo.webp` | Keian over a GCU mascot-and-brushwork backdrop, 1024x1536, white ground | `public/img/recipients/keian-cutout.png` |

## How the shipped versions were derived

**The crest.** The white ground is knocked out by flooding inward from the
borders, not by keying white globally — the lion's face is white too, and a
global key punches holes straight through it. The masthead copy is then cropped
above the pillars strip, which at header size is about three pixels tall and
reads as a smudge.

**The student.** Cut with rembg's `u2netp` model. Two others were tried and
rejected on screen: `u2net_human_seg` treats the GCU backdrop as part of the
subject and keeps all of it, and `isnet-general-use` leaves purple fragments
floating beside him. Alpha matting on top of `u2netp` makes it worse, not
better — it pulls the semi-transparent backdrop back in around the left arm.
The alpha is then contrast-stretched, low end to nothing and high end to solid
with a ramp between, which takes the leftover backdrop haze from 276k
semi-transparent pixels to 50k without stair-stepping the edges.

The GCU backdrop is dropped from the shipped image at the client's direction. If
it is ever wanted back, it is still here in `keian-gcu-promo.webp`.
