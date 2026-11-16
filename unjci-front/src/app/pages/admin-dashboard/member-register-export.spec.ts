import { Workbook } from 'exceljs';
import { createMemberRegisterWorkbook } from './member-register-export';

it('writes an Excel workbook that preserves row order, leading zeros and text values', async () => {
  const rows = [
    ['ÉBA', 'Awa', '', 'UJ26-00002', '', '00012', 'Renouvellement', 'Adhésion validée', '16/11/2026'],
    ['ZADI', '=1+1', '', '', 'UJ26-00003', '', 'Adhésion', 'Dossier à valider', '15/11/2026'],
  ];
  const workbook = new Workbook();
  await workbook.xlsx.load(await createMemberRegisterWorkbook(rows));
  const sheet = workbook.getWorksheet('Adhérents')!;
  expect(sheet.rowCount).toBe(3);
  expect(sheet.getRow(2).values).toEqual([undefined, ...rows[0]]);
  expect(sheet.getCell('A3').value).toBe('ZADI');
  expect(sheet.getCell('B3').value).toBe('=1+1');
});
