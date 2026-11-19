import { toDataURL } from 'qrcode';

export interface PrintableMember {
  first_name: string;
  last_name: string;
  member_number: string;
  press_card_number?: string;
  media_name?: string;
  employers?: string;
  photo_file_path?: string;
  qr_token: string;
}

export async function printMemberCards(
  preview: Window,
  members: PrintableMember[],
  verificationBase: string,
  logoUrl: string,
  loadPhoto: (path: string) => Promise<Blob>,
): Promise<void> {
  const doc = preview.document;
  preview.opener = null;
  const photoUrls = new Set<string>();
  preview.addEventListener('pagehide', () => {
    photoUrls.forEach(url => URL.revokeObjectURL(url));
    photoUrls.clear();
  }, { once: true });
  doc.documentElement.lang = 'fr';
  doc.title = 'Cartes des adhérents UNJCI';
  const viewport = doc.createElement('meta');
  viewport.name = 'viewport';
  viewport.content = 'width=device-width, initial-scale=1';
  const style = doc.createElement('style');
  style.textContent = `
    * { box-sizing: border-box; }
    body { margin: 1rem; background: #f1f3f4; color: #111; font: 16px Arial, sans-serif; }
    header { max-width: 52rem; margin: 0 auto 1rem; }
    button { padding: .55rem 1rem; font: inherit; cursor: pointer; }
    main { display: grid; gap: 1.25rem; max-width: 52rem; margin: auto; }
    .card-row { display: flex; justify-content: center; align-items: stretch; gap: 1.25rem; }
    article { display: flex; flex-direction: column; flex: 0 0 5.39cm; width: 5.39cm; height: 8.56cm;
      padding: 2.5mm; border-radius: 3mm; background: #fff; font-size: 9px;
      box-shadow: 0 .7em 1.8em #1118281c; break-inside: avoid; overflow-wrap: anywhere; }
    .card-heading { display: flex; align-items: center; justify-content: center; gap: .5em; margin-bottom: 1.6em;
      flex-shrink: 0; font-size: 0.56em; font-weight: 800; line-height: 1.2; text-align: center; white-space: nowrap; }
    .card-heading::before, .card-heading::after { content: ''; flex: 0 0 1.6em; height: 1px; background: #f58220; }
    .portrait { position: relative; isolation: isolate; display: grid; place-items: center;
      flex-shrink: 0; width: 56%; aspect-ratio: 1; margin: 0 auto .6em; }
    .portrait::before, .portrait::after { position: absolute; z-index: -1; content: '';
      width: 23%; aspect-ratio: 1; background: #f58220; }
    .portrait::before { top: -5%; right: -5%; }
    .portrait::after { bottom: -5%; left: -5%; }
    .portrait img, .initials { position: absolute; inset: 0; width: 100%; height: 100%; }
    .portrait img { object-fit: cover; }
    .initials { display: grid; place-items: center; background: #eef1ee; font-weight: 800; font-size: 2em; }
    h2 { margin: 0; color: #111; font-size: 1.35em; line-height: 1.1; font-weight: 900; }
    .function { margin: .3em 0 .4em; color: #19883d; font-size: 1.15em; font-weight: 800; text-align: center; }
    .function::before { content: ''; display: block; width: 3em; height: 1px; margin: 0 auto .3em; background: #f58220; }
    dl { display: grid; grid-template-columns: auto minmax(0,1fr); gap: .35em 1em;
      margin: 0 0 .5em; font-size: .82em; line-height: 1.1; }
    dt { color: #475569; }
    dd { margin: 0; font-weight: 800; }
    dd::before { content: ':'; float: left; margin-left: -.7em; font-weight: 400; }
    footer { display: flex; flex-shrink: 0; justify-content: space-between; align-items: flex-end; gap: .5em; margin-top: auto; }
    .qr { width: 27%; aspect-ratio: 1; }
    .logo { width: 40%; height: auto; }
    @page { size: A4 portrait; margin: 10mm; }
    @media print {
      header { display: none; }
      body { margin: 0; background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
      main { display: block; max-width: none; }
      .card-row { gap: 6mm; margin-bottom: 6mm; break-inside: avoid; }
      article { box-shadow: none; }
    }
    @media screen and (max-width: 640px) {
      .card-row { flex-direction: column; align-items: center; }
      article { flex-basis: auto; }
    }
  `;
  doc.head.append(viewport, style);
  const header = doc.createElement('header');
  const message = doc.createElement('p');
  message.setAttribute('role', 'status');
  message.textContent = 'Préparation des cartes…';
  const print = doc.createElement('button');
  print.type = 'button';
  print.textContent = 'Imprimer';
  print.disabled = true;
  print.addEventListener('click', () => preview.print());
  header.append(message, print);
  const list = doc.createElement('main');
  const format = doc.createElement('p');
  format.textContent = 'Format des cartes : 5,39 × 8,56 cm. Imprimez à 100 % (taille réelle).';
  header.append(format);
  doc.body.replaceChildren(header, list);

  async function createCard(member: PrintableMember): Promise<HTMLElement> {
    const card = doc.createElement('article');
    const heading = doc.createElement('div');
    heading.className = 'card-heading';
    heading.textContent = 'UNION NATIONALE DES JOURNALISTES DE CÔTE D’IVOIRE';
    const portrait = doc.createElement('div');
    portrait.className = 'portrait';
    const initials = doc.createElement('span');
    initials.className = 'initials';
    initials.textContent = `${member.first_name[0] ?? ''}${member.last_name[0] ?? ''}`;
    portrait.append(initials);
    const name = doc.createElement('h2');
    name.textContent = `${member.last_name} ${member.first_name}`.toLocaleUpperCase('fr');
    const position = doc.createElement('p');
    position.className = 'function';
    position.textContent = 'JOURNALISTE';
    const numbers = doc.createElement('dl');
    for (const [label, number] of [
      ['Organe de presse', member.media_name?.trim() || member.employers?.trim() || 'Non renseigné'],
      ['N° carte CIJP', member.press_card_number?.trim() || 'Non renseigné'],
      ['N° carte UNJCI', member.member_number],
    ]) {
      const term = doc.createElement('dt');
      term.textContent = label;
      const value = doc.createElement('dd');
      value.textContent = number;
      numbers.append(term, value);
    }
    card.append(heading, portrait, name, position, numbers);
    const footer = doc.createElement('footer');
    const qr = doc.createElement('img');
    qr.className = 'qr';
    qr.alt = 'QR de vérification';
    qr.src = await toDataURL(`${verificationBase}/verification/${encodeURIComponent(member.qr_token)}`, { width: 180 });
    const logo = doc.createElement('img');
    logo.className = 'logo';
    logo.alt = 'UNJCI';
    logo.src = logoUrl;
    footer.append(qr, logo);
    card.append(footer);
    const imageReady = [qr.decode(), logo.decode().catch(() => logo.remove())];
    if (member.photo_file_path) {
      const photo = doc.createElement('img');
      photo.alt = `Photo de ${member.first_name} ${member.last_name}`;
      imageReady.push(loadPhoto(member.photo_file_path).then(async blob => {
        if (preview.closed) return;
        const url = URL.createObjectURL(blob);
        photoUrls.add(url);
        try {
          photo.src = url;
          await photo.decode();
          portrait.replaceChildren(photo);
        } catch {
          URL.revokeObjectURL(url);
          photoUrls.delete(url);
        }
      }).catch(() => undefined));
    }
    await Promise.all(imageReady);
    return card;
  }

  try {
    for (let start = 0; start < members.length && !preview.closed; start += 6) {
      const cards = await Promise.all(members.slice(start, start + 6).map(createCard));
      for (let index = 0; index < cards.length; index += 2) {
        const row = doc.createElement('div');
        row.className = 'card-row';
        row.append(...cards.slice(index, index + 2));
        list.append(row);
      }
    }
    if (preview.closed) return;
    message.textContent = `${members.length} ${members.length === 1 ? 'carte prête' : 'cartes prêtes'} à imprimer.`;
    print.disabled = false;
    print.focus();
  } catch (error) {
    message.textContent = 'La préparation des cartes a échoué.';
    throw error;
  }
}
