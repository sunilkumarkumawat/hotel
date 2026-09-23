Put the hotel's own picture here to have it show behind the sidebar menu.

    public/images/sidebar.jpg

.jpeg, .png and .webp work too — whichever one is there is used. Delete the
file and the sidebar goes back to its plain colour. Nothing to switch on,
nothing to rebuild, and no need to restart anything: reload the page.

WHAT TO CHOOSE
  A calm, wide picture with no writing on it — a facade, a pool at dusk, a
  lobby. The menu sits on top of it, so anything busy in the middle of the
  frame fights with the text.

  Tall rather than wide: the sidebar is a narrow column about 206 pixels
  across and the full height of the screen. Something around 600 x 1600 is
  ideal. The picture is cropped to fill, centred, so the middle of the frame
  is what survives.

  Keep it under about 300 KB. It loads on every single page.

IF IT LOOKS WRONG
  The picture is deliberately held well back behind a dark wash, because white
  menu text over a photograph is unreadable otherwise. To show more or less of
  it, change one number in resources/css/app.css:

      .nv-sidebar[data-photo] { --nv-sidebar-dim: 0.84; }

  Lower shows more of the photo, higher hides it. Then run:

      php scripts/build-fallback.php
