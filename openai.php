<?php

require_once (dirname(__FILE__) . '/config.inc.php');

//----------------------------------------------------------------------------------------
function openai_call($url, $data)
{
	global $config;
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));  
	
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	
	curl_setopt($ch, CURLOPT_HTTPHEADER, 
		array(
			"Content-type: application/json",
			"Authorization: Bearer " . $config['openai_key']
			)
		);
	
	$response = curl_exec($ch);
	if($response == FALSE) 
	{
		$errorText = curl_error($ch);
		curl_close($ch);
		die($errorText);
	}
	
	$info = curl_getinfo($ch);
	$http_code = $info['http_code'];
	
	if (0)
	{
		print_r($info);
		echo $response;
	}
		
	curl_close($ch);
	
	return $response;
}

//----------------------------------------------------------------------------------------
// Get embedding (vector) for text
function get_embedding($text, $model = "text-embedding-ada-002")
{
	global $config;
	
	$embedding = array();
	
	$data = new stdclass;
	$data->model = $model;
	$data->input = $text;
	
	$response = openai_call($config['openai_embeddings'], $data);
	
	if ($response)
	{
		$obj = json_decode($response);
		if ($obj)
		{
			$embedding = $obj->data[0]->embedding;
		}
	} 	
	
	return $embedding;
}


//----------------------------------------------------------------------------------------
// Use ChatGPT to summarise the results to a question
function conversation ($prompt, $text)
{
	global $config;
	
	$summary = '';
			
	$model = "gpt-3.5-turbo";
	$model = "gpt-4o-mini";
	
	$data = new stdclass;
	$data->model = $model;
	$data->messages = array();
	
	$message = new stdclass;
	$message->role = "system";
	$message->content = $prompt;
	
	$data->messages[] = $message;
	
	$message = new stdclass;
	$message->role = "user";
	$message->content = $text;
	
	$data->messages[] = $message;
	
	// print_r($data);
	
	// echo json_encode($data);
	
	$response = openai_call($config['openai_completions'], $data);
	
	//echo $response;
	
	if ($response)
	{
		$obj = json_decode($response);
		if ($obj)
		{
			//print_r($obj);
			
			if (isset($obj->choices))
			{
				$summary = $obj->choices[0]->message->content;
			}
		}
	} 		
	
	return $summary;
}

//----------------------------------------------------------------------------------------


if (0)
{

	$text = "TWENTIETH-CENTURY ABORIGINAL HARVESTING PRACTICES IN THE RURAL

	LANDSCAPE OF THE LOWER MURRAY, SOUTH AUSTRALIA

	PA CLARKE

	CLARKE, PA. 2003. Twentieth-century Aboriginal harvesting practices in the rural
	landscape of the Lower Murray, South Australia. Records of the South Australian Museum
	36(1): 83-107.

	Since European settlement, Aboriginal people living in rural areas of southern South
	Australia have had a unique relation to the landscape, reflecting both pre-European indigenous
	traditions and post-European historical influences. Aboriginal hunting, fishing and gathering
	practices in the twentieth century were not relics of a pre-European past, but were derived from
	cultural forces that have produced a modern indigenous identity. The Lower Murray
	ethnographic data presented in this cultural geography study were collected mainly during the
	1980s, supplemented with historical information concerning earlier periods.

	PA Clarke, Science Division, South Australian Museum, North Terrace, Adelaide, South
	Australia 5000. Manuscript received 4 November 2002.";
	
	

	$text = "MALACOLOGIA, 1973, 12(1): 1-11

FEEDING AND ASSOCIATED FUNCTIONAL MORPHOLOGY
IN TAGELUS CALIFORNIANUS
AND FLORIMETIS OBESA (BIVALVIA: TELLINACEA)

Ross H. Pohlo

Department of Biology
California State University at Northridge
Northridge, California 91324, U.S.A.

ABSTRACT

";

	/*
	$text = "bo

	В. Н. POHLO

	lem

	FIG. 1. Organs of the mantle cavity of Tagelus californianus viewed from the right side. Right valve and mantle
	lobe removed. Arrows indicate the direction of particle movement. Dotted arrows indicate movement on the
	underside of the surface. AA—anterior adductor; CM—cruciform muscle; ES—exhalant siphon; F—foot;
	ID—inner demibranch; IS—inhalant siphon; L—ligament; L P—labial palp; ML—mantle lobe; OD—outer
	demibranch; PA—posterior adductor; PR—posterior retractor.

	(McLean, 1969).
	";
	*/

	/*
	$text = "J. Conon. 30: 303-304 (1981)

	NOTE ON THE IDENTITY OF FISSURELLA
	IMPEDIMENTUM COOKE, 1885
	(PROSOBRANCHIA: FISSURELLIDAE)

	Hen K K. MIENIS

	Zoological Museum, Mollusc Collection, Hebrew University of Jerusalem, Israel.

	(Read before the Society, 13 December, 1980)
	";
	*/
	
	$text = "REVUE SUISSE DE ZOOLOGIE

Tome 67, n° 27. — Septembre 1960.



:i2lj



Catalogue des Opisthobranches de la Rade

de Villefranche-sur-Mer et ses environs

(Alpes Maritimes)



par



Hans- Rudolf HAEFELFINGER



Station zoologique de Villefranche

et Zoologische Anstalt der Universitàt Basel 1



Avec 1 tableau et 2 cartes.



1. INTRODUCTION
";


$text = "J. Concu. 30: 317-323 (1981)

DIFFERENTIATION OF THE RADULA OF SOUTH
AFRICAN. SP EGIES (Fr oot GENUS, GULELIA
INTO THREE TYPES (GASTROPODA
PULMONATA: STREPTAXIDAE)

D. W. AIKEN

18 Pieter Raath Avenue, Lambton, Germiston, Transvaal, South Africa, 1401
(Read before the Society, 18 October, 1980)
";


	$prompt = "Extract article-level bibliographic metadata from the following text and return in RIS format. If no bibliographic metadata found return message \"No data\".";

	$prompt .= " The article is in the Journal of Conchology.";
	
	//$prompt .= " The text is in French.";

	$response = conversation ($prompt, $text);

	echo $response . "\n";
	
}

if (0)
{

	$text = "JOURNAL OF CONCHOLOGY, VOL. 30, NO. 5

	appearance of coming from the beach deposits of that area. Probably the form is extinct.’ I agree
	with his remark, however, I do not accept Newton’s opinion that the specimens described by
	him belong to the form group of Diodora ruppelli (Sowerby, 1835). Size, height, circular outline
	and sculpture are so different that I consider it a good species, of which the following synonymy
	is now known:

	Diodora impedimenta (Cooke, 1885)
	Fissurella umpedimentum Cooke, 1885: 270.
	Capulina ruppellu var. barront Newton, 1900: 502, pl. 22, figs. 1-4.

	ACKNOWLEDGEMENT

	I should like to thank Dr. C. B. Goodhart (Cambridge) for sending me on loan the type
	specimens of Fissurella impedimentum from the McAndrew collection.

	REFERENCES

	CuRISTIAENS, J., 1974. Le Genre Diodora (Gastropoda): Especes non-européennes. Inf. Soc. Belge Malac., 3 (6-7): 73-97,
	3 pls.

	Cooke, A. H., 1885. Report on the testaceous Mollusca obtained during a dredging-excursion in the Gulf of Suez in the
	months of February and March 1869 by R. MacAndrew. Republished, with additions and corrections by A. H.
	Cooke. Ann. Mag. nat. Hist., 16: 262-276.

	Newton, R. B., 1900. Pleistocene shells from the raised beach deposits of the Red Sea. Geol. Mag. ser. 4, 7: 500-514,

	544-560, pls20 and 22:

	PLATE 12 (opposite)

	Syntypes of Fissurella impedimentum Cooke, Figs. 1-3, UMZC 2354/2; figs. 4-5, UMZC 2354/1; figs. 6-7, UMZC
	2354/4; fig. 8, UMZC 2354/3. All x5.

	304



	";


	$prompt = "Extract article-level bibliographic metadata from the following text and return in RIS format. If no bibliographic metadata found return message \"No data\".";

	$prompt .= " The article is in the Joiurnal of Conchology.";

	$prompt = "Extract a bibliography of bibliographoc references cited in this text. Output list in RIS format: ";


	$response = conversation ($prompt, $text);

	echo $response . "\n";
}


if (0)
{
	$text = 'Summary
This position is a Geneticist, GS- 0440-12 working in Lamar, Pennsylvania
for the R5-Lamar NFH and Northeast Fishery Center.

Duties
The Northeast Fishery Center, located in Lamar, Pennsylvania. Lamar is
located in a rural area surrounded by agriculture, state forest and game
lands, high-quality fishing streams, and near college towns including
State College, PA, the home of Penn State University, and Lock Haven,
PA where Lock Haven University is located.

The Northeast Fishery Center includes both the Lamar Fish Technology
Center and Lamar Fish Health Center. The Lamar Fish Technology Center
provides research capabilities and technical expertise in areas including
fish culture, population dynamics, and conservation genetics. The
Conservation Genetics Lab works closely with partners in the FWS and
elsewhere to apply genetic methods to issues conservation, and focuses on
population genetics, environmental DNA, and genomics applications. The
Conservation Genetics Lab works with partners to develop, conduct,
and interpret genetics projects. Genetic projects include monitoring
estimates of genetic diversity, defining populations, identifying species,
and conducting environmental DNA analysis and research in the lab and
field. The duties for this position include, but are not limited to:

- Coordinate, lead, and manage genetics projects focusing on
  metabarcoding applications
- Develop and lead research related to application of genetic methods
  for detection of invasive species bioinformatics analysis resulting
  from metabarcoding data
- Provide technical expertise for genetic data analysis and data
  management
- Provide technical assistance and conduct genetic sequence alignments
  for genomic sequencing
- Provide overall technical coordination and interpretation and conduct
  complex molecular genetics analyses for a variety of projects
- Provide oral presentation at workshops, symposia, and other
  scientific meetings

Requirements
Conditions of Employment

  *   Must be a U.S. Citizen or National.
  *   Suitability for employment, as determined by background
      investigation.
  *   Probationary Period: Selectees may be required to successfully
      complete a probationary period.
  *   Individuals assigned male at birth after 12-31-59 must be
      registered for Selective Service. To verify registration,
      visit SSS.gov.
  *   Driver\'s License: Selectees MAY be required to possess and
      maintain a valid State driver\'s license at all times during
      their tenure.
  *   Uniform: Official U.S. Fish and Wildlife Service uniform MAY
      be required.

Qualifications
Only experience and education obtained by the closing date 01/03/2025
will be considered.

In order to qualify for this position you must possess both the Basic
Requirement and Minimum Qualification.

Basic Requirement: Possess a degree with a major in genetics; or one
of the basic biological sciences that included at least 9 semester
hours in genetics.Graduate Education: Genetics, or a curriculum or
pattern of training that placed major emphasis on genetics. Graduate
study in related fields such as agronomy, horticulture, animal, dairy,
or poultry husbandry, entomology, microbiology, plant pathology,
chemistry, molecular and cellular biology, and physiology that involved
cross-training in genetics is qualifying, provided it placed sufficient
emphasis on genetics.

Minimum Qualification [GS-12]:
One year of professional experience equivalent to the GS-11 level in
the Federal service. Examples of qualifying specialized experience may
include: 1) Conduct complex molecular genetic analyses for a variety of
projects working with different species and genetic analysis methods
(e.g. environmental DNA analyses including quantitative PCR and
metabarcoding, or to determine genetic similarities and relationships,
assign individuals to population of origin, quantify levels of genetic
variation within and among populations, and identify species and sex);
2) Perform statistical analyses and generate graphical representations of
study results, and incorporate the data into written reports, scientific
publications, and oral presentations; 3) Oversee and schedule laboratory
activities performed by 1-2 biological technicians, students, and/or
volunteers; and 4) Use DNA markers and automated DNA analyzers/sequencers
to collect genotypic, gene frequency, bioinformatic classification of
metabarcoding data, and DNA sequence data. NOTE: Your resume must contain
sufficient information in these areas to be found qualified.

Experience refers to paid and unpaid experience, including volunteer work
done through National Service programs (e.g., Peace Corps, AmeriCorps)
and other organizations (e.g., professional; philanthropic; religious;
spiritual; community, student, social). Volunteer work helps build
critical competencies, knowledge, and skills and can provide valuable
training and experience that translates directly to paid employment. You
will receive credit for all qualifying experience, including volunteer
experience.

Education
PROOF OF EDUCATION: All applicants who are using education or a
combination of education and experience to qualify must submit copies
of official or unofficial transcripts which include grades, credit hours
earned, major(s), grade point average or class ranking, institution name,
and student name. If any required coursework is not easily recognizable
on transcripts, or if you believe a portion of a particular course can
be credited toward meeting an educational requirement, you must also
provide a memorandum on letterhead from the institution\'s registrar, dean,
or other appropriate official stating the percentage of the course that
should be considered to meet the requirement and the equivalent number
of units. Unofficial transcripts are acceptable; however, if you are
selected for the position, you will be required to produce the original
official transcripts.

PASS/FAIL COURSES: If more than 10 percent of your undergraduate course
work (credit hours) were taken on a pass/fail basis, your claim of
superior academic achievement must be based upon class standing or
membership in an honor society.

GRADUATE EDUCATION: One academic year of graduate education is considered
to be the number of credits hours your graduate school has determined
to represent one academic year of full-time study. Such study may have
been performed on a full-time or part-time basis. If you cannot obtain
your graduate school\'s definition of one year of graduate study, 18
semester hours (or 27 quarter hours) should be considered as satisfying
the requirement for one year of full-time graduate study.

FOREIGN EDUCATION: If you are using education completed in foreign
colleges or universities to meet the qualification requirements, you
must show the education credentials have been evaluated by a private
organization that specializes in interpretation of foreign education
programs and such education has been deemed equivalent to that gained
in an accredited U.S. education program; or full credit has been given
for the courses at a U.S. accredited college or university.

Your qualifications will be evaluated on the following competencies
(knowledge, skills, abilities and other characteristics):
- Knowledge of the scientific method in applied biological research to
  design, implement, and conduct genetic analyses resulting from
  genetic data.
- Skill and ability in written communication in order to ensure the
  quality of technical reports and manuscripts and in communication with
  other professionals and the public; and to identify and coordinate
  research activities to assist in management decisions.
- Knowledge of project planning and design for complex genetics analyses
  for application to conservation issues.
- Knowledge of statistical methods for analyzing genetic data.
- Knowledge of collection and management of genetic samples and data.

For further information and details of how to apply, please visit
https://www.usajobs.gov/job/825223400 (annoucement for Open to all
U.S. Citizens. ICTAP/CTAP eligible) https://www.usajobs.gov/job/825226200
(annoucement for Government Wide: Current Career or Career Conditional
Federal Employees, Land Management eligible, 30% disabled veterans,
Military Spouse, Peace Corps, AmeriCorps, Vista, VEOA, ICTAP/CTAP,
Schedule A, Reinstatement eligible, Public Land Corp (PLC))

Stacey Nerkowski, PhD

Regional Geneticist
Northeast Fishery Center Complex
U.S. Fish and Wildlife Service
308 Washington Ave.
Lamar, PA 16848
work: 570-726-4247 ext 50138
cell: 570-927-0073
stacey_nerkowski@fws.gov
Pronouns: she/her

stacey_nerkowski@fws.gov

(to subscribe/unsubscribe the EvolDir send mail to
golding@mcmaster.ca)';


	$prompt = "Summarise this message in 200 characters, and include one relevant URL (if present)";

	$response = conversation ($prompt, $text);

	echo $response . "\n";



}

?>
