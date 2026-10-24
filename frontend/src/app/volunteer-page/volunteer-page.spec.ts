import { ComponentFixture, TestBed } from '@angular/core/testing';
import { VolunteerPage } from './volunteer-page';

describe('VolunteerPage', () => {
  let component: VolunteerPage;
  let fixture: ComponentFixture<VolunteerPage>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [VolunteerPage],
    }).compileComponents();

    fixture = TestBed.createComponent(VolunteerPage);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
