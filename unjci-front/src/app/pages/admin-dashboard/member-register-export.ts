import { Workbook } from 'exceljs';

export async function createMemberRegisterWorkbook(rows: string[][]): Promise<ArrayBuffer> {
  const workbook = new Workbook();
  const sheet = workbook.addWorksheet('Adhérents', { views: [{ state: 'frozen', ySplit: 1 }] });
  sheet.columns = [
    { header: 'Nom', width: 26 },
    { header: 'Prénoms', width: 28 },
    { header: 'E-mail', width: 38 },
    { header: 'N° UNJCI officiel', width: 24 },
    { header: 'N° UNJCI déclaré', width: 24 },
    { header: 'N° CIJP', width: 21 },
    { header: 'Demande', width: 21 },
    { header: 'Statut', width: 24 },
    { header: 'Date de demande', width: 22 },
  ];
  sheet.addRows(rows);
  sheet.autoFilter = 'A1:I1';
  sheet.getRow(1).font = { bold: true, color: { argb: 'FFFFFFFF' } };
  sheet.getRow(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF075A43' } };
  return workbook.xlsx.writeBuffer();
}
