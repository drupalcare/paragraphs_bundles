(function (Drupal, once) {
  Drupal.behaviors.fixtureCard = {
    attach(context) {
      once('fixture-card', '.pb__card', context).forEach((el) => {
        el.classList.toggle('pb__active');
      });
    },
  };
})(Drupal, once);
