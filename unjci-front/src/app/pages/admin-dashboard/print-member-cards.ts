import { toDataURL } from 'qrcode';

export interface PrintableMember {
  first_name: string;
  last_name: string;
  member_number: string;
  press_card_number?: string;
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
    body { margin: 1rem; background: #f1f3f4; color: #17241f; font: 16px system-ui, sans-serif; }
    header { max-width: 52rem; margin: 0 auto 1rem; }
    button { padding: .55rem 1rem; font: inherit; cursor: pointer; }
    main { display: grid; gap: 1.25rem; max-width: 52rem; margin: auto; }
    .card-row { display: flex; justify-content: center; align-items: stretch; gap: 1.25rem; }
    article { display: flex; flex-direction: column; flex: 0 1 25rem; min-width: 0;
      padding: 2.5em 1.875em; border-radius: 1em; background: #fff;
      box-shadow: 0 .7em 1.8em #1118281c; break-inside: avoid; overflow-wrap: anywhere; }
    .portrait { position: relative; isolation: isolate; display: grid; place-items: center;
      width: 81%; aspect-ratio: 1; margin: 0 auto 2.5em; }
    .portrait::before, .portrait::after { position: absolute; z-index: -1; content: '';
      width: 23%; aspect-ratio: 1; background: #f58220; }
    .portrait::before { top: -7%; right: -5%; }
    .portrait::after { bottom: -7%; left: -5%; }
    .portrait img, .initials { position: absolute; inset: 0; width: 100%; height: 100%; }
    .portrait img { object-fit: cover; }
    .initials { display: grid; place-items: center; background: #eef1ee; font-weight: 800; font-size: 2em; }
    h2 { margin: 0; color: #111; font-size: 1.875em; line-height: 1.2; font-weight: 900; }
    .function { margin: .25em 0 1em; color: #475569; font-size: 1.25em; }
    .badge { align-self: flex-start; padding: .45em 2.1em; border: 2px solid #dc8b3c;
      border-radius: 99px; color: #b76b24; font-weight: 800; letter-spacing: .06em; }
    dl { display: grid; grid-template-columns: auto minmax(0,1fr); gap: .3em .75em;
      margin: .9em 0 1.7em; font-size: .9em; }
    dt { color: #475569; }
    dd { margin: 0; font-weight: 750; }
    footer { display: flex; justify-content: space-between; align-items: flex-end; gap: .5em; margin-top: auto; }
    .qr { width: 32%; aspect-ratio: 1; }
    .logo { width: 34%; height: auto; }
    @page { size: A4 portrait; margin: 10mm; }
    @media print {
      header { display: none; }
      body { margin: 0; background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
      main { display: block; max-width: none; }
      .card-row { gap: 6mm; margin-bottom: 6mm; break-inside: avoid; }
      article { flex: 0 1 88mm; padding: 1.9em 1.6em; border: 1px solid #dce1df; box-shadow: none; font-size: 12px; }
    }
    @media screen and (max-width: 640px) {
      .card-row { flex-direction: column; align-items: center; }
      article { width: min(100%, 25rem); flex-basis: auto; font-size: clamp(12px, 4vw, 16px); }
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
  doc.body.replaceChildren(header, list);

  async function createCard(member: PrintableMember): Promise<HTMLElement> {
    const card = doc.createElement('article');
    const portrait = doc.createElement('div');
    portrait.className = 'portrait';
    const initials = doc.createElement('span');
    initials.className = 'initials';
    initials.textContent = `${member.first_name[0] ?? ''}${member.last_name[0] ?? ''}`;
    portrait.append(initials);
    const name = doc.createElement('h2');
    name.textContent = `${member.last_name.toLocaleUpperCase('fr')} ${member.first_name}`;
    const position = doc.createElement('p');
    position.className = 'function';
    position.textContent = 'Journaliste';
    const badge = doc.createElement('span');
    badge.className = 'badge';
    badge.textContent = 'MEMBRE';
    const numbers = doc.createElement('dl');
    for (const [label, number] of [
      ['N° carte CIJP', member.press_card_number?.trim() || 'Non renseigné'],
      ['N° carte UNJCI', member.member_number],
    ]) {
      const term = doc.createElement('dt');
      term.textContent = label;
      const value = doc.createElement('dd');
      value.textContent = number;
      numbers.append(term, value);
    }
    card.append(portrait, name, position, badge, numbers);
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
