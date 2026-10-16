(function (Drupal, once) {
  Drupal.behaviors.fixtureCard = {
    attach(context) {
      once('fixture-card', '.pb-card', context).forEach((el) => {
        el.classList.toggle('pb-is-active');
      });
    },
  };
})(Drupal, once);
