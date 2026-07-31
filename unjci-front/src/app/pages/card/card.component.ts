import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import QRCode from 'qrcode';
import { MemberService } from '../../core/member.service';
import { MemberApplication } from '../../core/member.model';

@Component({selector:'app-card-page',standalone:true,imports:[CommonModule,RouterLink],templateUrl:'./card.component.html',styleUrl:'./card.component.css'})
export class CardComponent implements OnInit {
  member?: MemberApplication;
  qrDataUrl = '';
  constructor(private members: MemberService) {}
  async ngOnInit() {
    this.member = this.members.getLatest();
    if (this.member) {
      const url = `${location.origin}/verification/${this.member.qrToken}`;
      this.qrDataUrl = await QRCode.toDataURL(url, { width: 320, margin: 1, errorCorrectionLevel: 'H' });
    }
  }
  printCard(){ window.print(); }
}
