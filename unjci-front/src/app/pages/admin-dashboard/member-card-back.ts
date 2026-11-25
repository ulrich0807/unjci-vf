export const MEMBER_CARD_BACK_STYLES = `
  article.card-back {
    position: relative; isolation: isolate; justify-content: space-between; padding: 3.5mm;
    border: .25mm solid #cfdfd5; color: #173d30;
    background: linear-gradient(145deg, #ffffff, #edf7f1); overflow: hidden;
  }
  .card-back::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 1.2mm;
    background: linear-gradient(90deg, #f58220 0 30%, #168343 30% 100%);
  }
  .back-brand { text-align: center; }
  .back-logo { display: block; width: 20mm; height: auto; margin: 0 auto 1.5mm; }
  .back-brand p { margin: 0; font-size: 0.76em; font-weight: 800; line-height: 1.4; letter-spacing: .035em; }
  .back-edition { display: inline-block; margin-top: 1.5mm; padding: .6mm 2mm; border: .2mm solid #168343; border-radius: 2mm; color: #126438; font-size: .9em; font-weight: 800; letter-spacing: .1em; }
  .back-copy p { margin: 0; font-family: inherit; font-size: 1em; font-weight: 400; line-height: 1.45; }
  .back-copy-english { padding-top: 2.3mm; border-top: .2mm solid #cbded1; }
  .card-back .back-contact { display: block; border-top: .2mm solid #cbded1; padding-top: 2mm; text-align: center; }
  .back-contact strong { display: block; color: #126438; font-size: 1.05em; }
`;

export async function createMemberCardBack(doc: Document, logoUrl: string): Promise<HTMLElement> {
  const card = doc.createElement('article');
  card.className = 'card-back';
  card.setAttribute('aria-label', 'Verso de la carte UNJCI');

  const brand = doc.createElement('div');
  brand.className = 'back-brand';
  const logo = doc.createElement('img');
  logo.className = 'back-logo';
  logo.src = logoUrl;
  logo.alt = 'UNJCI';
  const title = doc.createElement('p');
  title.append('UNION NATIONALE DES JOURNALISTES', doc.createElement('br'), 'CÔTE D’IVOIRE');
  const edition = doc.createElement('span');
  edition.className = 'back-edition';
  edition.textContent = 'UNJCI 2026';
  brand.append(logo, title, edition);

  function section(language: string, text: string, className: string): HTMLElement {
    const section = doc.createElement('section');
    section.className = className;
    section.lang = language;
    const paragraph = doc.createElement('p');
    paragraph.textContent = text;
    section.append(paragraph);
    return section;
  }

  const french = section('fr',
    'L’Union Nationale des Journalistes de Côte d’Ivoire prie d’accorder au porteur de la présente toute l’aide et toutes les facilités utiles à l’exercice de la profession.',
    'back-copy');
  const english = section('en',
    'The National Union of Journalists of Côte d’Ivoire requests that the bearer be extended every assistance and courtesy in the performance of this mission.',
    'back-copy back-copy-english');
  const contact = doc.createElement('footer');
  contact.className = 'back-contact';
  const email = doc.createElement('strong');
  email.textContent = 'contacts@unjci.ci';
  contact.append(email);
  card.append(brand, french, english, contact);
  await logo.decode().catch(() => logo.remove());
  return card;
}
